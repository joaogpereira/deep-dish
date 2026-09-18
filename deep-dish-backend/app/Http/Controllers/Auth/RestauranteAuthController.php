<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\RestauranteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class RestauranteAuthController extends Controller
{
    public function __construct(private RestauranteService $service) {}

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'regex:/^[^@]+@[^@]+\.(com)$/i',
                'unique:restaurante,email',
            ],
            'cnpj' => 'required|string|unique:restaurante,cnpj',
            'tipo' => 'required|string|in:brasileira,italiana,japonesa,chinesa,árabe,mexicana,francesa,portuguesa,churrasco,frutos do mar,vegetariano,vegano,fast food,pizza,hamburguer,cafeteria,padaria,comida caseira',
            'logradouro' => 'required|string|max:255',
            'numero' => 'required|string|max:20',
            'complemento' => 'nullable|string|max:100',
            'bairro' => 'required|string|max:100',
            'cidade' => 'required|string|max:100',
            'estado' => 'required|string|size:2',
            'cep' => ['required', 'string', 'regex:/^\d{5}-?\d{3}$/'],
            'telefone' => 'nullable|string',
            'imagem_url' => 'nullable|string',
            'fila_ativa' => 'nullable|boolean',
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
                'message' => 'Restaurante cadastrado com sucesso!',
                'restaurante' => $result['restaurante']->makeVisible(['email', 'cnpj']),
                'type' => 'restaurante',
                'token' => $result['token'],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Erro ao cadastrar restaurante', [
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

        if (! $token = auth('restaurante')->attempt($credentials)) {
            return response()->json([
                'error' => 'Email ou senha inválidos',
            ], 401);
        }

        $user = auth('restaurante')->user();
        if (! $user->hasVerifiedEmail()) {
            $cacheKey = 'verify_sent_restaurante_'.$user->id;
            if (! \Cache::has($cacheKey)) {
                $user->sendEmailVerificationNotification();
                \Cache::put($cacheKey, true, now()->addMinutes(2));
            }
        }

        return response()->json([
            'message' => 'Login realizado com sucesso',
            'type' => 'restaurante',
            'token' => $token,
        ]);
    }

    public function me()
    {
        $restaurante = auth('restaurante')->user();

        return response()->json($restaurante->makeVisible(['email', 'cnpj']));
    }

    // ─── Atualiza perfil do restaurante ─────────────────────
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'telefone' => 'sometimes|nullable|string',
            'horario_abertura' => 'sometimes|nullable|required_with:horario_fechamento|date_format:H:i',
            'horario_fechamento' => 'sometimes|nullable|required_with:horario_abertura|date_format:H:i',
            'fila_ativa' => 'sometimes|boolean',
            'logradouro' => 'sometimes|string|max:255',
            'numero' => 'sometimes|string|max:20',
            'complemento' => 'sometimes|nullable|string|max:100',
            'bairro' => 'sometimes|string|max:100',
            'cidade' => 'sometimes|string|max:100',
            'estado' => 'sometimes|string|size:2',
            'cep' => ['sometimes', 'string', 'regex:/^\d{5}-?\d{3}$/'],
            // TODO: 'rating' hoje é auto-declarado pelo próprio restaurante, sem
            // nenhuma verificação. O ideal é substituir por uma média calculada a
            // partir de avaliações reais de clientes (ex.: liberadas após reserva
            // com status 'liberada', no mesmo padrão do histórico de reservas que
            // já existe) — e parar de aceitar esse valor diretamente aqui.
            'rating' => 'sometimes|nullable|numeric|min:0|max:5',
            'price_range' => 'sometimes|nullable|integer|min:1|max:4',
            'reservations_enabled' => 'sometimes|boolean',
            'description' => 'sometimes|nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Dados de entrada inválidos',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $restaurante = auth('restaurante')->user();
            $restaurante->update($validator->validated());

            return response()->json($restaurante->makeVisible(['email', 'cnpj']));
        } catch (\Throwable $e) {
            Log::error('Erro ao atualizar restaurante', ['message' => $e->getMessage()]);

            return response()->json(['error' => 'Erro interno no servidor'], 500);
        }
    }

    // ─── Upload de imagem do restaurante ────────────────────
    public function uploadImagem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'imagem' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Imagem inválida',
                'details' => $validator->errors(),
            ], 422);
        }

        $restaurante = auth('restaurante')->user();
        $caminhoAntigo = $restaurante->getRawOriginal('imagem_url');

        // Disco padrão (FILESYSTEM_DISK): 'public' local, 'supabase' em produção.
        // O banco guarda só o caminho relativo; a URL sai do accessor do model.
        $caminho = $request->file('imagem')->store('restaurantes');

        if (! $caminho) {
            return response()->json(['error' => 'Não foi possível salvar a imagem'], 500);
        }

        $restaurante->update(['imagem_url' => $caminho]);

        // A antiga só sai depois que a nova foi salva — se o upload falhar, o
        // restaurante não fica sem imagem. URL legada (http...) não é arquivo nosso.
        if ($caminhoAntigo && ! str_starts_with($caminhoAntigo, 'http')) {
            Storage::delete($caminhoAntigo);
        }

        return response()->json([
            'message' => 'Imagem atualizada com sucesso!',
            'imagem_url' => $restaurante->imagem_url,
        ]);
    }

    public function logout()
    {
        $user = auth('restaurante')->user();

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
            'token' => auth('restaurante')->refresh(),
        ]);
    }
}
