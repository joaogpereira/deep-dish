<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Restaurante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    // GET /email/verify/{tipo}/{id}/{hash}  (signed URL — chamada pelo link do e-mail)
    public function verify(Request $request, string $tipo, string $id, string $hash): RedirectResponse
    {
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');

        $model = $tipo === 'restaurante'
            ? Restaurante::find($id)
            : Cliente::find($id);

        if (! $model || ! hash_equals(sha1($model->getEmailForVerification()), (string) $hash)) {
            return redirect("{$frontendUrl}/verify-email?error=invalid");
        }

        if (! $model->hasVerifiedEmail()) {
            $model->markEmailAsVerified();
        }

        return redirect("{$frontendUrl}/verify-email?verified=1&tipo={$tipo}");
    }

    // POST /cliente/email/resend   (auth:api)
    // POST /restaurante/email/resend  (auth:restaurante)
    public function resend(Request $request, string $tipo): JsonResponse
    {
        $user = $tipo === 'restaurante'
            ? auth('restaurante')->user()
            : auth('api')->user();

        if (! $user) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'E-mail já verificado.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'E-mail de verificação reenviado.']);
    }
}
