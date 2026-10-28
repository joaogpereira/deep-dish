<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ClienteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClienteAuthController extends Controller
{
    public function __construct(private ClienteService $service) {}

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:cliente,email',
            'cpf' => 'required|string|unique:cliente,cpf',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Dados de entrada inválidos',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->service->register($validator->validated());

            return response()->json([
                'message' => 'Cliente cadastrado com sucesso!',
                'cliente' => $result['cliente'],
                'type' => 'cliente',
                'token' => $result['token'],
            ], 201);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erro ao cadastrar cliente', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Erro interno no servidor',
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors(),
            ], 422);
        }

        $credentials = $request->only('email', 'password');

        if (! $token = auth('api')->attempt($credentials)) {
            return response()->json([
                'error' => 'Email ou senha inválidos',
            ], 401);
        }

        $user = auth('api')->user();
        if (! $user->hasVerifiedEmail()) {
            $cacheKey = 'verify_sent_cliente_'.$user->id;
            if (! \Cache::has($cacheKey)) {
                $user->sendEmailVerificationNotification();
                \Cache::put($cacheKey, true, now()->addMinutes(2));
            }
        }

        return response()->json([
            'message' => 'Login realizado com sucesso',
            'type' => 'cliente',
            'token' => $token,
        ]);
    }

    public function me()
    {
        return response()->json(auth('api')->user());
    }

    public function logout()
    {
        $user = auth('api')->user();

        if ($user) {
            $user->increment('token_version');
        }

        return response()->json([
            'message' => 'Logout realizado com sucesso',
        ]);
    }

    public function refresh()
    {
        return response()->json([
            'token' => auth('api')->refresh(),
        ]);
    }
}
