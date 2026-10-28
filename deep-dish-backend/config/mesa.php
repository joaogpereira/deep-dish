<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tolerância da limpeza (#171)
    |--------------------------------------------------------------------------
    |
    | Minutos que a mesa pode ficar em 'aguardando_limpeza' ou 'em_limpeza' antes
    | de a 'mesas:liberar-limpeza' devolvê-la para 'livre' por conta própria.
    |
    | A rede existe porque o fluxo de limpeza coloca duas ações humanas entre a
    | saída do cliente e a mesa voltar a receber alguém. Sem ela, um esquecimento
    | da equipe travaria a fila para sempre.
    |
    | Padrão: 5 minutos — curto de propósito. Prioriza o atendimento não parar
    | sobre a métrica encher: ciclo liberado automaticamente fica fora da média de
    | tempo de limpeza, porque mediria o esquecimento, não a limpeza.
    |
    */

    'tolerancia_limpeza_minutos' => (int) env('MESA_TOLERANCIA_LIMPEZA_MINUTOS', 5),

];
