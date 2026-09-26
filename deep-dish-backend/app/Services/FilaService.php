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
use App\Models\Restaurante;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
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
            // A flag existe desde sempre no cadastro do restaurante, mas nada a
            // consultava: dava para entrar na fila de quem a mantém desligada.
            $filaAtiva = Restaurante::query()->whereKey($restauranteId)->value('fila_ativa');

            if (! $filaAtiva) {
                throw new InvalidArgumentException('A fila deste restaurante está fechada no momento.');
            }

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
     * Por que o cliente saiu da fila deste horário, ou null se nunca esteve nela.
     *
     * Complementa o 404 de consultarPosicao: sem isso, a tela do cliente não
     * distingue "foi promovido para mesa" de "foi removido" e precisava adivinhar
     * listando reservas. Ignora o status da fila de propósito — promover o último
     * da fila a encerra.
     */
    public function statusSaida(
        string $clienteId,
        string $restauranteId,
        string $horarioReserva
    ): ?string {
        return ClienteFila::withTrashed()
            ->where('cliente_id', $clienteId)
            ->whereNotNull('status_saida')
            ->whereHas('fila', fn ($q) => $q
                ->where('restaurante_id', $restauranteId)
                ->where('horario_reserva', Carbon::parse($horarioReserva)))
            ->latest('saiu_em')
            ->value('status_saida');
    }

    /**
     * ÚNICO ponto de promoção da fila: varre as mesas disponíveis do restaurante
     * e chama quem couber. Todo caminho que libera lugar entra por aqui —
     * liberar, cancelar, expirar reserva ou chamada, desbloquear e criar mesa.
     *
     * Antes só o "liberar" do painel chamava alguém: cancelamento e expiração
     * devolviam a mesa e a fila não andava.
     *
     * A transação trava a linha do restaurante. Duas promoções simultâneas no
     * mesmo salão viram fila, em vez de darem a mesma mesa a dois clientes —
     * o status da mesa não serve de trava, porque ela só sai de 'livre' no
     * check-in, bem depois da chamada.
     *
     * @return Collection<int, ClienteMesa> as reservas criadas
     */
    public function processarPromocoes(string $restauranteId): Collection
    {
        return DB::transaction(function () use ($restauranteId) {
            $restaurante = Restaurante::query()->whereKey($restauranteId)->lockForUpdate()->first();

            if (! $restaurante?->fila_ativa) {
                return collect();
            }

            $mesas = $this->mesasDisponiveis($restauranteId);

            if ($mesas->isEmpty()) {
                return collect();
            }

            $promovidos = collect();
            $filasTocadas = [];

            foreach ($this->escolherAlocacoes($restauranteId, $mesas) as [$entrada, $mesa]) {
                $promovidos->push($this->promover($entrada, $mesa));
                $filasTocadas[(string) $entrada->fila_id] = true;
            }

            if ($promovidos->isNotEmpty()) {
                FilaAtualizada::dispatch($restauranteId);
                // Nasceram reservas: as telas de Mesas e Reservas do painel mudaram.
                OperacaoAtualizada::dispatch($restauranteId);

                foreach (array_keys($filasTocadas) as $filaId) {
                    PosicaoFilaAtualizada::dispatch($filaId);
                }
            }

            return $promovidos;
        });
    }

    /**
     * Mesas que podem receber alguém agora.
     *
     * 'livre' não basta: a mesa continua 'livre' entre a chamada e o check-in, e
     * pode ter reserva marcada para daqui a pouco. Por isso também exige nenhuma
     * reserva ativa sobrepondo a janela [agora, agora + duração].
     *
     * @return Collection<int, Mesa>
     */
    private function mesasDisponiveis(string $restauranteId): Collection
    {
        $agora = now();
        $fim = $agora->copy()->addMinutes(ClienteMesa::DURACAO_RESERVA_MINUTOS);

        return Mesa::query()
            ->where('restaurante_id', $restauranteId)
            ->where('status', 'livre')
            ->whereDoesntHave('clienteMesas', fn ($q) => $q->ativasSobrepondo($agora, $fim))
            ->orderBy('capacidade')
            ->orderBy('numero')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Quem senta em qual mesa. Hoje mantém a regra antiga — FIFO estrito, o
     * primeiro da fila cabe ou a mesa fica vazia — só que aplicada a todas as
     * mesas disponíveis de uma vez, e não a uma só.
     *
     * É este método que a #169 troca pelo best-fit, acabando com o bloqueio que
     * um grupo grande na frente causa hoje.
     *
     * @param  Collection<int, Mesa>  $mesas
     * @return list<array{0: ClienteFila, 1: Mesa}>
     */
    private function escolherAlocacoes(string $restauranteId, Collection $mesas): array
    {
        $entradas = $this->entradasAguardando($restauranteId);
        $alocacoes = [];

        foreach ($mesas as $mesa) {
            $proximo = $entradas->first();

            if (! $proximo) {
                break;
            }

            if ($mesa->capacidade < $proximo->qntd_pessoas) {
                continue;
            }

            $alocacoes[] = [$proximo, $mesa];
            $entradas->shift();
        }

        return $alocacoes;
    }

    /**
     * Fila do restaurante, da entrada mais antiga para a mais nova, travada para
     * esta transação. Junta todas as filas abertas: a ordem é por chegada, não
     * por horário de reserva.
     *
     * @return Collection<int, ClienteFila>
     */
    private function entradasAguardando(string $restauranteId): Collection
    {
        return ClienteFila::query()
            ->ativas()
            ->whereHas('fila', fn ($q) => $q
                ->where('restaurante_id', $restauranteId)
                ->where('status', Fila::STATUS_ABERTA)
            )
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /** Tira a entrada da fila e cria a reserva da chamada. */
    private function promover(ClienteFila $entrada, Mesa $mesa): ClienteMesa
    {
        $clienteMesa = ClienteMesa::create([
            'cliente_id' => $entrada->cliente_id,
            'mesa_id' => $mesa->id,
            'horario_reserva' => now()->utc(),
            'party_size' => $entrada->qntd_pessoas,
            'status' => 'confirmada',
        ]);

        $entrada->registrarChamada($clienteMesa);

        $this->encerrarFilaSeVazia($entrada->fila);

        // Aviso pessoal: quem foi promovido nao descobre pelo canal da fila,
        // que so diz que a composicao mudou.
        ClientePromovido::dispatch(
            (string) $entrada->cliente_id,
            (string) $clienteMesa->id,
        );

        return $clienteMesa;
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
        $restaurantesAfetados = [];

        foreach ($candidatas as $id) {
            $expiradas += (int) DB::transaction(function () use ($id, &$restaurantesAfetados) {
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
                // mexe), então não há o que desfazer. A mesa volta para a fila
                // depois do loop, pelo ponto único — que confere sozinho se ela
                // continua disponível (o restaurante pode tê-la bloqueado).
                $mesa = $reserva->mesa;

                ReservaAtualizada::dispatch((string) $reserva->cliente_id);
                if ($mesa) {
                    $restaurantesAfetados[(string) $mesa->restaurante_id] = true;
                    OperacaoAtualizada::dispatch((string) $mesa->restaurante_id);
                }

                return true;
            });
        }

        foreach (array_keys($restaurantesAfetados) as $restauranteId) {
            $this->processarPromocoes($restauranteId);
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
