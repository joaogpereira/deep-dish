<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenRouter (#177)
    |--------------------------------------------------------------------------
    |
    | Unica saida do projeto para um LLM. OpenRouter em vez da API direta de um
    | fornecedor foi decisao do backlog: sai mais barato e o modelo troca por env,
    | sem mexer em codigo.
    |
    | 'model' NAO tem padrao de proposito. O catalogo de modelos do OpenRouter
    | muda, e chutar um slug aqui daria erro 404 silencioso na primeira chamada —
    | pior que a falha explicita de "modelo nao configurado". Pegue o slug e o
    | preco na lista de modelos do OpenRouter e ponha no .env.
    |
    | 'timeout' curto porque a requisicao do usuario espera por esta chamada: e
    | melhor desistir e dizer que o assistente esta fora do que pendurar a tela.
    |
    */

    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY'),
        'model' => env('OPENROUTER_MODEL'),
        // Lista separada por virgula, em ordem de prioridade. O OpenRouter troca de
        // modelo sozinho quando o primeiro falha — inclusive por rate limit, que no
        // plano gratuito chega rapido (20/min, 50/dia). Vazio = sem fallback.
        'models_fallback' => env('OPENROUTER_MODELS_FALLBACK'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'timeout' => (int) env('OPENROUTER_TIMEOUT_SEGUNDOS', 20),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
