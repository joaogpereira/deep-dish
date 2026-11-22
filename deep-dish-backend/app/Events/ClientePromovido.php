<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * "Sua mesa esta pronta": o cliente saiu da fila e ganhou uma reserva.
 *
 * Vai no canal pessoal, nao no da fila, porque a promocao e de uma pessoa so —
 * no canal da fila todos receberiam o aviso de uma mesa que nao e deles.
 *
 * Isto e o aviso para quem esta com a tela aberta. Notificacao com o app fechado
 * depende de push (FCM), que e outro item do Sprint 3.
 */
class ClientePromovido implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $clienteId,
        public string $reservaId,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('cliente.'.$this->clienteId);
    }

    public function broadcastAs(): string
    {
        return 'cliente.promovido';
    }
}
