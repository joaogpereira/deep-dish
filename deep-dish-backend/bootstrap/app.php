<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // A rota /broadcasting/auth nasce com o guard de sessao, que este projeto nao usa.
    // Aqui ela passa a aceitar os dois guards JWT e a revalidar o token_version —
    // sem isso, um token de quem ja deslogou continuaria assinando canal privado.
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        [
            // Sob /api para casar com o VITE_API_URL que o frontend ja usa.
            'prefix' => 'api',
            'middleware' => [
                'api',
                'auth:api,restaurante',
                \App\Http\Middleware\VerifyJwtTokenVersion::class,
            ],
        ],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // No Laravel 11 o grupo 'api' não tem limite nenhum por padrão: sem isto,
        // qualquer endpoint aceita requisições em rajada — inclusive o login, que
        // ficava aberto a força bruta. Teto geral de 60 por minuto por IP
        // (autenticado, por usuário); as rotas sensíveis apertam mais em api.php.
        $middleware->throttleApi();

        $middleware->alias([
            'cliente.or.restaurante' => \App\Http\Middleware\ClienteOuRestaurante::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e) {
            return response()->json(['error' => 'Não autenticado.'], 401);
        });
    })->create();
