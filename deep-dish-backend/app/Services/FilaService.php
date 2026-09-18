<?php

namespace App\Services;

use App\Events\ClientePromovido;
use App\Events\FilaAtualizada;
use App\Events\OperacaoAtualizada;
use App\Events\PosicaoFilaAtualizada;
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

            $proximo->registrarSaida(ClienteFila::STATUS_SAIDA_ATENDIDO);

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
