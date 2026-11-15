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

    /*
    |--------------------------------------------------------------------------
    | Contexto enviado ao modelo (#179)
    |--------------------------------------------------------------------------
    |
    | Quantos turnos anteriores (pergunta + resposta) voltam para o modelo. A
    | conversa inteira fica no banco; so o fim dela vai na requisicao, porque
    | cada turno a mais e pago de novo em toda pergunta seguinte.
    |
    */

    'historico_turnos' => (int) env('ASSISTENTE_HISTORICO_TURNOS', 6),

    /*
    |--------------------------------------------------------------------------
    | Orcamento por restaurante (#179)
    |--------------------------------------------------------------------------
    |
    | Teto de tokens por restaurante por dia (fuso America/Sao_Paulo). A checagem
    | acontece antes da pergunta, entao a ultima pergunta do dia pode passar um
    | pouco do teto: o custo dela so e conhecido depois da resposta.
    |
    */

    'teto_tokens_dia' => (int) env('ASSISTENTE_TETO_TOKENS_DIA', 200000),

    /*
    |--------------------------------------------------------------------------
    | Rate limit e trava de pergunta em andamento (#179)
    |--------------------------------------------------------------------------
    |
    | O limite por minuto segura uso abusivo. A trava e o que impede o clique
    | repetido de pagar a mesma pergunta duas vezes: enquanto uma pergunta do
    | restaurante esta sendo respondida, a proxima e recusada. A duracao da trava
    | so importa se o processo morrer no meio — normalmente ela e solta ao fim
    | da resposta — e precisa cobrir o pior caso: max_iteracoes x timeout.
    |
    */

    'requisicoes_por_minuto' => (int) env('ASSISTENTE_REQUISICOES_POR_MINUTO', 10),

    'trava_segundos' => (int) env('ASSISTENTE_TRAVA_SEGUNDOS', 120),

];
