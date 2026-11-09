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

    /*
    |--------------------------------------------------------------------------
    | Priorização por encaixe (#170)
    |--------------------------------------------------------------------------
    |
    | A ordem base da fila é a de chegada. O encaixe só a flexibiliza quando uma
    | mesa que vagou seria muito desperdiçada pelo próximo grupo e outro grupo,
    | de trás, a aproveita bem (ver FilaService::escolherAlocacoes).
    |
    | desperdicio_maximo_lugares (X): lugares ociosos que um grupo ainda pode
    |   "causar" sem ser preterido. Acima disso, se outro grupo na fila encaixa
    |   na mesma mesa dentro do limite e desperdiça menos, ele a recebe. Com 0, só
    |   encaixe exato é aceito sem disputa. Padrão: 2 — um casal numa mesa de 4
    |   não perde a vez; numa de 10, perde para um grupo de 8.
    |
    | espera_protegida_minutos (Y): depois de tanto tempo na fila, a entrada vira
    |   protegida — é atendida antes de qualquer otimização e nunca mais é
    |   ultrapassada por quem chegou depois. É o limite para ganhar prioridade,
    |   NÃO uma garantia de atendimento em Y minutos: sem mesa que comporte o
    |   grupo, ele segue esperando. Padrão: 20 minutos.
    |
    */

    'desperdicio_maximo_lugares' => (int) env('FILA_DESPERDICIO_MAXIMO_LUGARES', 2),

    'espera_protegida_minutos' => (int) env('FILA_ESPERA_PROTEGIDA_MINUTOS', 20),

];
