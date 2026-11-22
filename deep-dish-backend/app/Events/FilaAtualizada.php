<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sinaliza ao painel do restaurante que a fila mudou (entrada, saida, remocao
 * ou promocao). Carrega so o id do restaurante, nao a fila: o frontend refaz o
 * GET /restaurante/fila, que ja calcula as posicoes em memoria. Assim o payload
 * nao carrega dado de cliente e nao reintroduz o N+1 de posicao no broadcast.
 *
 * ShouldDispatchAfterCommit: os pontos de disparo estao dentro de transacoes;
 * sem isso o frontend refaria a consulta antes do commit e veria a fila antiga.
 */
class FilaAtualizada implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public string $restauranteId) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('restaurante.'.$this->restauranteId);
    }

    public function broadcastAs(): string
    {
        return 'fila.atualizada';
    }
}
