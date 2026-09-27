<?php

namespace App\Broadcasting;

use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Broadcasting\BroadcastException;

/**
 * Broadcaster do Reverb que nao derruba a requisicao quando o Reverb esta fora.
 *
 * Com QUEUE_CONNECTION=database o envio roda no worker e a falha fica la
 * (retry + failed_jobs) — nada muda nesse caso. Com 'sync', o envio roda dentro
 * da propria requisicao, depois do commit: sem isto, liberar uma mesa gravaria
 * a mudanca e ainda assim responderia 500.
 *
 * Os eventos sao so avisos de "recarregue"; o frontend cai no polling quando o
 * socket esta fora, entao perder o aviso e aceitavel. Perder o registro nao e:
 * a falha continua indo para o log via report().
 */
class BroadcasterTolerante extends PusherBroadcaster
{
    public function broadcast(array $channels, $event, array $payload = [])
    {
        try {
            parent::broadcast($channels, $event, $payload);
        } catch (BroadcastException $e) {
            if (config('queue.default') !== 'sync') {
                throw $e;
            }

            report($e);
        }
    }
}
