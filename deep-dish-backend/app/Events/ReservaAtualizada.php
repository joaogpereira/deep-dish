<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Alguma reserva do cliente mudou de estado: check-in feito, mesa liberada,
 * cancelada ou expirada.
 *
 * Substitui os dois ultimos pollings do app do cliente — o da Home (lista de
 * reservas ativas) e o do ReservationDetail (uma reserva). Como e um sinal, as
 * duas telas usam o mesmo evento: so a que estiver montada refaz a consulta.
 */
class ReservaAtualizada implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public string $clienteId) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('cliente.'.$this->clienteId);
    }

    public function broadcastAs(): string
    {
        return 'reserva.atualizada';
    }
}
