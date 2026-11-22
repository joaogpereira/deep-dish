<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api') ?? $request->user('restaurante');

        if ($user && ! $user->hasVerifiedEmail()) {
            return response()->json([
                'error' => 'email_not_verified',
                'message' => 'Verifique seu e-mail antes de continuar.',
            ], 403);
        }

        return $next($request);
    }
}
