<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tolerância da chamada (#12)
    |--------------------------------------------------------------------------
    |
    | Minutos entre a chamada para a mesa (promoção da fila) e o check-in antes
    | de o cliente ser considerado no-show. Passado esse prazo, a
    | 'fila:expirar-chamados' marca a entrada como 'expirado', expira a reserva
    | e passa a mesa para o próximo da fila.
    |
    | Padrão: 15 minutos — dá tempo de quem esperava por perto voltar ao salão
    | sem segurar a mesa por muito tempo.
    |
    */

    'tolerancia_chamada_minutos' => (int) env('FILA_TOLERANCIA_CHAMADA_MINUTOS', 15),

];
