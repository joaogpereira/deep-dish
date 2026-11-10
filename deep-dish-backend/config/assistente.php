<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Teto de iteracoes do loop de tool-calling (#178)
    |--------------------------------------------------------------------------
    |
    | Cada iteracao e uma ida ao modelo. Sem teto, uma pergunta ambigua pode
    | virar chamada infinita — o modelo pede ferramenta, le o resultado, pede de
    | novo, e cada volta custa tokens e tempo de quem esta esperando na tela.
    |
    | 4 da espaco para o caminho normal (pedir ferramenta, ler, responder) e para
    | uma correcao quando o modelo erra o argumento na primeira tentativa, sem
    | deixar a conta aberta.
    |
    */

    'max_iteracoes' => (int) env('ASSISTENTE_MAX_ITERACOES', 4),

    /*
    |--------------------------------------------------------------------------
    | Janela padrao das ferramentas
    |--------------------------------------------------------------------------
    |
    | Quantos dias as ferramentas olham quando o usuario nao diz o periodo.
    | Mesmo valor do AnalyticsController, para o assistente e o grafico da tela
    | nao responderem coisas diferentes para a mesma pergunta.
    |
    */

    'dias_padrao' => (int) env('ASSISTENTE_DIAS_PADRAO', 90),

];
