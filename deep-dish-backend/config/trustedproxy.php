<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proxies confiáveis
    |--------------------------------------------------------------------------
    |
    | Quem o rate limiter conta como "um IP" depende de qual IP o Laravel enxerga.
    | Sem proxy na frente, o IP é o do cliente e está tudo certo — é o caso do
    | docker-compose, que expõe o `artisan serve` direto. É por isso que o padrão
    | aqui é null: não confiar em ninguém.
    |
    | Atrás de um proxy (nginx, Render, Railway, Cloudflare), o IP que chega é o
    | do proxy, e aí TODA a aplicação divide um único balde de 60/min — usuário
    | legítimo tomando 429. Para corrigir, aponte este valor para o IP ou a faixa
    | do proxy, via TRUSTED_PROXIES no .env; o Laravel passa a ler o IP real do
    | cabeçalho X-Forwarded-For.
    |
    | Cuidado com '*': ele confia no X-Forwarded-For de qualquer origem. Se a
    | aplicação estiver alcançável sem passar pelo proxy, qualquer um forja o
    | cabeçalho, ganha um balde novo a cada requisição e o rate limit inteiro
    | deixa de existir. Só use '*' quando o proxy for o único caminho de entrada.
    |
    | Aceita uma lista separada por vírgula: TRUSTED_PROXIES=10.0.0.1,10.0.0.2
    |
    */

    // O ?: null importa: com a variavel presente e vazia no .env, env() devolve
    // string vazia, e o middleware registraria [''] como proxy confiavel.
    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
