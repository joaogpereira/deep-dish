<?php

namespace App\Services;

use App\Events\ClientePromovido;
use App\Events\FilaAtualizada;
use App\Events\OperacaoAtualizada;
use App\Events\PosicaoFilaAtualizada;
use App\Events\ReservaAtualizada;
use App\Models\ClienteFila;
use App\Models\ClienteMesa;
use App\Models\Fila;
use App\Models\Mesa;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FilaService
{
    public function enfileirar(
        string $clienteId,
        string $restauranteId,
        string $horarioReserva,
        int $qntdPessoas
    ): ClienteFila {
        return DB::transaction(function () use ($clienteId, $restauranteId, $horarioReserva, $qntdPessoas) {
            // BUG CORRIGIDO: sem o cliente_id, o primeiro da fila bloqueava todos os outros.
            $jaEmFila = ClienteFila::ativas()
                ->where('cliente_id', $clienteId)
                ->whereHas('fila', fn ($q) => $q
                    ->where('restaurante_id', $restauranteId)
                    ->where('status', Fila::STATUS_ABERTA)
                )
                ->exists();

            if ($jaEmFila) {
                throw new InvalidArgumentException('Você já está na fila deste restaurante.');
            }

            $horario = Carbon::parse($horarioReserva);

            // firstOrCreate + unique em (restaurante_id, horario_reserva) evita fila duplicada
            try {
                $fila = Fila::firstOrCreate(
                    [
                        'restaurante_id' => $restauranteId,
                        'horario_reserva' => $horario,
                        'status' => Fila::STATUS_ABERTA,
                    ]
                );
            } catch (QueryException $e) {
                // corrida perdida: outra requisição criou a fila entre o select e o insert
                $fila = Fila::query()
                    ->where('restaurante_id', $restauranteId)
                    ->where('horario_reserva', $horario)
                    ->where('status', Fila::STATUS_ABERTA)
                    ->firstOrFail();
            }

            try {
                $registro = ClienteFila::create([
                    'fila_id' => $fila->id,
                    'cliente_id' => $clienteId,
                    'qntd_pessoas' => $qntdPessoas,
                ]);

                FilaAtualizada::dispatch($restauranteId);
                PosicaoFilaAtualizada::dispatch((string) $fila->id);

                return $registro;
            } catch (QueryException $e) {
                // violação do índice parcial único (fila_id, cliente_id) WHERE status_saida IS NULL
                throw new InvalidArgumentException('Você já está na fila deste restaurante.');
            }
        });
    }

    public function cancelarPosicao(string $clienteFilaId, string $clienteId): bool
    {
        return DB::transaction(function () use ($clienteFilaId, $clienteId) {
            $registro = ClienteFila::query()
                ->ativas()
                ->whereKey($clienteFilaId)
                ->where('cliente_id', $clienteId)
                ->lockForUpdate()
                ->first();

            if (! $registro) {
                throw new InvalidArgumentException('Posição não encontrada ou já processada.');
            }

            $fila = $registro->fila;

            $registro->registrarSaida(ClienteFila::STATUS_SAIDA_DESISTIU);

            $this->encerrarFilaSeVazia($fila);

            FilaAtualizada::dispatch((string) $fila->restaurante_id);
            PosicaoFilaAtualizada::dispatch((string) $fila->id);

            return true;
        });
    }

    public function consultarPosicao(
        string $clienteId,
        string $restauranteId,
        string $horarioReserva
    ): ?ClienteFila {
        $horario = Carbon::parse($horarioReserva);

        $fila = Fila::query()
            ->where('restaurante_id', $restauranteId)
            ->where('horario_reserva', $horario)
            ->where('status', Fila::STATUS_ABERTA)
            ->first();

        if (! $fila) {
            return null;
        }

        $registro = ClienteFila::query()
            ->ativas()
            ->where('fila_id', $fila->id)
            ->where('cliente_id', $clienteId)
            ->first();

        // 'posicao' saiu do $appends do model (era N+1 em listagens);
        // aqui a posição é o objetivo da chamada, então anexamos explicitamente.
        return $registro?->append('posicao');
    }

    /**
     * Promove o próximo da fila para uma mesa que acabou de ser liberada.
     * Busca a entrada mais antiga entre todas as filas abertas do restaurante.
     */
    public function promoverProximoParaMesa(string $restauranteId, Mesa $mesa): ?ClienteMesa
    {
        return DB::transaction(function () use ($restauranteId, $mesa) {
            $proximo = ClienteFila::query()
                ->ativas()
                ->whereHas('fila', fn ($q) => $q
                    ->where('restaurante_id', $restauranteId)
                    ->where('status', Fila::STATUS_ABERTA)
                )
                // OPÇÃO B — "chama o próximo que caiba na mesa":
                // ->where('qntd_pessoas', '<=', $mesa->capacidade)
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $proximo) {
                return null;
            }

            // OPÇÃO A (atual) — respeita FIFO estrito: se o primeiro não cabe, ninguém é chamado.
            // Remova este bloco se adotar a OPÇÃO B acima.
            if ($mesa->capacidade < $proximo->qntd_pessoas) {
                return null;
            }

            $clienteMesa = ClienteMesa::create([
                'cliente_id' => $proximo->cliente_id,
                'mesa_id' => $mesa->id,
                'horario_reserva' => now()->utc(),
                'party_size' => $proximo->qntd_pessoas,
                'status' => 'confirmada',
            ]);

            $fila = $proximo->fila;

            $proximo->registrarChamada($clienteMesa);

            $this->encerrarFilaSeVazia($fila);

            FilaAtualizada::dispatch($restauranteId);
            PosicaoFilaAtualizada::dispatch((string) $fila->id);
            // Nasceu uma reserva: as telas de Mesas e Reservas do painel mudaram.
            OperacaoAtualizada::dispatch($restauranteId);

            // Aviso pessoal: quem foi promovido nao descobre pelo canal da fila,
            // que so diz que a composicao mudou.
            ClientePromovido::dispatch(
                (string) $proximo->cliente_id,
                (string) $clienteMesa->id,
            );

            return $clienteMesa;
        });
    }

    /**
     * Expira quem foi chamado para a mesa e não fez check-in dentro da
     * tolerância (config 'fila.tolerancia_chamada_minutos'): a entrada vira
     * 'expirado', a reserva da chamada expira e a mesa vai para o próximo da
     * fila. Retorna quantas entradas expiraram.
     *
     * Idempotente: o que já expirou deixa de casar com a consulta. Entrada sem
     * 'chamado_em' nunca é tocada — quem ainda espera, ou saiu por outro
     * caminho, não foi chamado.
     *
     * "Confirmação" hoje é o check-in feito pelo restaurante
     * (ReservaController::checkin), o único sinal de chegada que existe.
     *
     * Feature futura: confirmação pelo cliente no app ("estou chegando"), logo
     * após a chamada. Com ela, quem confirmou ganharia mais prazo e quem não
     * respondeu poderia expirar antes, liberando a mesa mais cedo. Precisaria
     * de um endpoint do cliente, de uma coluna própria (ex.: 'confirmado_em')
     * e de um botão na tela de reserva.
     */
    public function expirarChamadosSemConfirmacao(): int
    {
        $limite = now()->subMinutes((int) config('fila.tolerancia_chamada_minutos'));

        $candidatas = ClienteFila::withTrashed()
            ->where('status_saida', ClienteFila::STATUS_SAIDA_ATENDIDO)
            ->whereNotNull('chamado_em')
            ->where('chamado_em', '<=', $limite)
            ->whereHas('reservaDaChamada', fn ($q) => $q->where('status', 'confirmada'))
            ->pluck('id');

        $expiradas = 0;

        foreach ($candidatas as $id) {
            $expiradas += (int) DB::transaction(function () use ($id) {
                $entrada = ClienteFila::withTrashed()->lockForUpdate()->find($id);
                $reserva = ClienteMesa::with('mesa')->lockForUpdate()->find($entrada?->clientemesa_id);

                // O check-in pode ter chegado entre a consulta e o lock.
                if (! $reserva || $reserva->status !== 'confirmada') {
                    return false;
                }

                $reserva->update(['status' => 'expirada']);
                $reserva->registrarSaida();
                $entrada->registrarNaoComparecimento();

                // A reserva da chamada não mexe no status da mesa (só o check-in
                // mexe), então não há o que desfazer. A mesa só vai para o
                // próximo se continuar livre — o restaurante pode tê-la
                // bloqueado nesse meio-tempo.
                $mesa = $reserva->mesa;
                if ($mesa && $mesa->status === 'livre') {
                    $this->promoverProximoParaMesa((string) $mesa->restaurante_id, $mesa);
                }

                ReservaAtualizada::dispatch((string) $reserva->cliente_id);
                if ($mesa) {
                    OperacaoAtualizada::dispatch((string) $mesa->restaurante_id);
                }

                return true;
            });
        }

        return $expiradas;
    }

    /**
     * Quantos clientes ativos há na fila de um restaurante para um horário específico.
     * Devolve 0 quando a fila ainda nem existe — usado para estimar a posição de
     * quem ainda não entrou (FilaController::estimativa).
     */
    public function contarAtivos(string $restauranteId, string $horarioReserva): int
    {
        $horario = Carbon::parse($horarioReserva);

        $fila = Fila::query()
            ->where('restaurante_id', $restauranteId)
            ->where('horario_reserva', $horario)
            ->where('status', Fila::STATUS_ABERTA)
            ->first();

        if (! $fila) {
            return 0;
        }

        return ClienteFila::query()->ativas()->where('fila_id', $fila->id)->count();
    }

    /** Público: o FilaController::removerRestaurante também precisa desta regra. */
    public function encerrarFilaSeVazia(Fila $fila): void
    {
        $temNaFila = ClienteFila::query()
            ->ativas()
            ->where('fila_id', $fila->id)
            ->exists();

        if (! $temNaFila) {
            $fila->update(['status' => Fila::STATUS_ENCERRADA]);
        }
    }
}
