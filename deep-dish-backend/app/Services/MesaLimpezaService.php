<?php

namespace App\Services;

use App\Events\OperacaoAtualizada;
use App\Models\Mesa;
use App\Models\MesaLimpeza;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * O ciclo de limpeza da mesa (#171).
 *
 * Entre "cliente saiu" e "mesa pronta" existe um intervalo que o sistema não
 * registrava: 'liberar' devolvia a mesa para 'livre' no mesmo instante. Agora a
 * mesa passa por 'aguardando_limpeza' e 'em_limpeza', e cada passagem grava uma
 * linha em 'mesa_limpeza' para o Analytics poder medir onde o tempo se perde.
 *
 * Quem muda o status é sempre este serviço, nunca o controller direto, para o
 * histórico não ficar para trás do estado da mesa.
 */
class MesaLimpezaService
{
    /**
     * Cliente saiu: a mesa vira 'aguardando_limpeza' e o ciclo abre.
     *
     * Só vale para mesa 'ocupada', e isso é a regra inteira: 'ocupada' é escrito
     * apenas no check-in (ReservaController::checkin), então significa "alguém
     * sentou de fato". No-show nunca passa por 'ocupada' e, portanto, não pede
     * limpeza — o guard dispensa qualquer checagem de check-in aqui.
     *
     * Devolve false quando a mesa não estava ocupada, para o chamador decidir o
     * que fazer (os caminhos de saída do ReservaController mandam para 'livre').
     */
    public function marcarParaLimpeza(Mesa $mesa): bool
    {
        return (bool) DB::transaction(function () use ($mesa) {
            $atual = Mesa::query()->lockForUpdate()->find($mesa->id);

            if (! $atual || $atual->status !== Mesa::STATUS_OCUPADA) {
                return false;
            }

            $atual->update(['status' => Mesa::STATUS_AGUARDANDO_LIMPEZA]);

            MesaLimpeza::create([
                'mesa_id' => $atual->id,
                'aguardando_desde' => now(),
            ]);

            $mesa->setAttribute('status', Mesa::STATUS_AGUARDANDO_LIMPEZA);

            return true;
        });
    }

    /** Equipe começou a limpar: marca o relógio da limpeza em si. */
    public function iniciarLimpeza(Mesa $mesa): void
    {
        DB::transaction(function () use ($mesa) {
            $atual = Mesa::query()->lockForUpdate()->findOrFail($mesa->id);

            if ($atual->status !== Mesa::STATUS_AGUARDANDO_LIMPEZA) {
                throw new InvalidArgumentException('Só é possível iniciar a limpeza de uma mesa aguardando limpeza.');
            }

            $atual->update(['status' => Mesa::STATUS_EM_LIMPEZA]);

            $this->cicloAberto($atual)?->update(['iniciada_em' => now()]);

            $mesa->setAttribute('status', Mesa::STATUS_EM_LIMPEZA);
        });
    }

    /**
     * Mesa pronta: volta para 'livre' e fecha o ciclo.
     *
     * Aceita os dois estados de limpeza — pular o 'em_limpeza' é legítimo ("já
     * estava limpa"), e nesse caso 'duracao_segundos' fica nulo de propósito: não
     * houve limpeza para cronometrar, e um zero mentiria na média.
     */
    public function concluirLimpeza(Mesa $mesa, bool $automatica = false): void
    {
        DB::transaction(function () use ($mesa, $automatica) {
            $atual = Mesa::query()->lockForUpdate()->findOrFail($mesa->id);

            if (! in_array($atual->status, Mesa::STATUS_LIMPEZA, true)) {
                throw new InvalidArgumentException('Esta mesa não está em limpeza.');
            }

            $atual->update(['status' => Mesa::STATUS_LIVRE]);

            $this->fecharCiclo($atual, $automatica);

            $mesa->setAttribute('status', Mesa::STATUS_LIVRE);
        });
    }

    /**
     * Devolve para 'livre' toda mesa que passou da tolerância em limpeza, e manda a
     * fila andar.
     *
     * A rede existe porque o fluxo coloca duas ações humanas entre a saída do
     * cliente e a mesa voltar a receber alguém; sem ela, um esquecimento travaria a
     * fila para sempre.
     *
     * Mesmo desenho de FilaService::expirarChamadosSemConfirmacao: ids primeiro,
     * uma transação com lock por mesa revalidando o estado dentro do lock (a equipe
     * pode ter marcado entre a consulta e o lock), e processarPromocoes só DEPOIS do
     * loop — sem essa chamada a mesa volta para 'livre' e a fila não anda.
     *
     * @return int quantas mesas foram liberadas
     */
    public function liberarVencidas(): int
    {
        $limite = now()->subMinutes((int) config('mesa.tolerancia_limpeza_minutos'));

        $candidatas = Mesa::query()
            ->whereIn('status', Mesa::STATUS_LIMPEZA)
            ->whereHas('limpezas', fn ($q) => $q
                ->whereNull('finalizada_em')
                ->where('aguardando_desde', '<=', $limite)
            )
            ->pluck('id');

        $liberadas = 0;
        $restaurantesAfetados = [];

        foreach ($candidatas as $id) {
            $liberadas += (int) DB::transaction(function () use ($id, &$restaurantesAfetados) {
                $mesa = Mesa::query()->lockForUpdate()->find($id);

                // A equipe pode ter concluído a limpeza entre a consulta e o lock.
                if (! $mesa || ! in_array($mesa->status, Mesa::STATUS_LIMPEZA, true)) {
                    return false;
                }

                $mesa->update(['status' => Mesa::STATUS_LIVRE]);

                $this->fecharCiclo($mesa, true);

                $restaurantesAfetados[(string) $mesa->restaurante_id] = true;
                OperacaoAtualizada::dispatch((string) $mesa->restaurante_id);

                return true;
            });
        }

        foreach (array_keys($restaurantesAfetados) as $restauranteId) {
            app(FilaService::class)->processarPromocoes($restauranteId);
        }

        return $liberadas;
    }

    /** O ciclo que ainda não fechou, se houver. */
    private function cicloAberto(Mesa $mesa): ?MesaLimpeza
    {
        return MesaLimpeza::query()
            ->where('mesa_id', $mesa->id)
            ->whereNull('finalizada_em')
            ->orderByDesc('aguardando_desde')
            ->first();
    }

    /**
     * Fecha o ciclo em aberto.
     *
     * 'duracao_segundos' só é gravado quando houve 'iniciada_em', espelhando
     * ClienteMesa::registrarSaida: sem início não houve limpeza cronometrada, e um
     * zero entraria na média como se tivesse havido.
     */
    private function fecharCiclo(Mesa $mesa, bool $automatica): void
    {
        $ciclo = $this->cicloAberto($mesa);

        if (! $ciclo) {
            return;
        }

        $agora = now();

        $ciclo->update([
            'finalizada_em' => $agora,
            // (int) abs(...) como ClienteMesa::registrarSaida: diffInSeconds
            // devolve float nesta versao do Carbon, e a coluna e integer.
            'duracao_segundos' => $ciclo->iniciada_em
                ? (int) abs($ciclo->iniciada_em->diffInSeconds($agora))
                : null,
            'liberada_automaticamente' => $automatica,
        ]);
    }
}
