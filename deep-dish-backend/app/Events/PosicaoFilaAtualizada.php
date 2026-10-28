<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Avisa a quem esta numa fila que a composicao dela mudou, e portanto a propria
 * posicao pode ter mudado.
 *
 * O canal e por fila, nao por cliente, de proposito: uma unica mensagem atende
 * todo mundo da fila. Emitir um evento por cliente daria N jobs por mudanca e
 * traria de volta, pelo lado do broadcast, o N+1 de posicao que o PR #188 matou.
 *
 * Nao carrega posicao: cada cliente consulta a sua em GET /fila/posicao. Assim
 * ninguem recebe dado de outro cliente pelo socket.
 */
class PosicaoFilaAtualizada implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public string $filaId) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('fila.'.$this->filaId);
    }

    public function broadcastAs(): string
    {
        return 'posicao.atualizada';
    }
}
