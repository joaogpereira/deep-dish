<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Função de middleware para verificar se o usuario é restaurante e logo pode acessar qualquer página que um cliente consegue.

class ClienteOuRestaurante
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth('api')->check() || auth('cliente')->check()) {
            return $next($request);
        }
        if (auth('restaurante')->check()) {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized'], 401);
    }
}
