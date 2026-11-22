<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class VerifyJwtTokenVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user()
            ?? auth('cliente')->user()
            ?? auth('restaurante')->user();

        if ($user) {
            try {
                $payload = JWTAuth::parseToken()->getPayload();
                $tokenVersion = $payload->get('token_version', 0);

                if ((int) ($user->token_version ?? 0) !== (int) $tokenVersion) {
                    return response()->json([
                        'message' => 'Token inválido, faça login novamente.',
                    ], 401);
                }
            } catch (\Throwable $e) {
                return response()->json([
                    'message' => 'Token inválido, faça login novamente.',
                ], 401);
            }
        }

        return $next($request);
    }
}
