<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
    // POST /cliente/forgot-password
    // POST /restaurante/forgot-password
    public function forgotPassword(Request $request, string $tipo): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $broker = $tipo === 'restaurante' ? 'restaurantes' : 'clientes';
        $status = Password::broker($broker)->sendResetLink(['email' => $request->email]);

        // Sempre retorna 200 para não revelar se o e-mail existe na base
        return response()->json([
            'message' => 'Se este e-mail estiver cadastrado, você receberá um link de recuperação em breve.',
        ]);
    }

    // POST /cliente/reset-password
    // POST /restaurante/reset-password
    public function resetPassword(Request $request, string $tipo): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $broker = $tipo === 'restaurante' ? 'restaurantes' : 'clientes';

        $status = Password::broker($broker)->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => $password])->save();
                if (! $user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                }
                $user->increment('token_version');
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Senha redefinida com sucesso! Faça login com a nova senha.']);
        }

        return response()->json([
            'message' => 'Link inválido ou expirado. Solicite um novo link de recuperação.',
        ], 422);
    }
}
