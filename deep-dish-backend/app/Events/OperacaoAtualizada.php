<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Mudou o estado do salao: status de mesa ou de reserva.
 *
 * Um evento so para as duas coisas porque elas mudam juntas — um check-in altera
 * a reserva e ocupa a mesa na mesma operacao — e porque as telas que consomem
 * (Mesas, Reservas, Dashboard) sao rotas distintas: apenas a que estiver montada
 * refaz a consulta. Separar em dois eventos daria mais codigo e o mesmo resultado.
 */
class OperacaoAtualizada implements ShouldBroadcast, ShouldDispatchAfterCommit
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
        return 'operacao.atualizada';
    }
}
