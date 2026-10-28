<?php

namespace App\Http\Controllers;

use App\Models\Funcionario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FuncionarioController extends Controller
{
    public function publicIndex(string $id): JsonResponse
    {
        $funcionarios = Funcionario::where('restaurante_id', $id)
            ->where('ativo', true)
            ->orderBy('name')
            ->get(['name', 'cargo', 'horario']);

        return response()->json($funcionarios);
    }

    public function index(Request $request): JsonResponse
    {
        $restauranteId = auth('restaurante')->id();
        $perPage = min((int) $request->query('per_page', 15), 50);
        $status = $request->query('status');  // 'ativo' | 'inativo' | null
        $cargo = $request->query('cargo');

        $query = Funcionario::where('restaurante_id', $restauranteId);

        if ($status === 'ativo') {
            $query->where('ativo', true);
        } elseif ($status === 'inativo') {
            $query->where('ativo', false);
        }

        if ($cargo) {
            $query->where('cargo', $cargo);
        }

        return response()->json($query->orderBy('name')->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cargo' => ['required', 'string', 'max:255'],
            'cpf' => ['required', 'string', 'max:14'],
            'telefone' => ['required', 'string', 'max:20'],
            'data_nascimento' => ['required', 'date'],
            'horario' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'observacoes' => ['nullable', 'string'],
            'ativo' => ['boolean'],
        ]);

        $funcionario = Funcionario::create([
            'restaurante_id' => auth('restaurante')->id(),
            ...$validated,
        ]);

        return response()->json($funcionario, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $funcionario = Funcionario::where('id', $id)
            ->where('restaurante_id', auth('restaurante')->id())
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'cargo' => ['sometimes', 'string', 'max:255'],
            'cpf' => ['nullable', 'string', 'max:14'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'data_nascimento' => ['nullable', 'date'],
            'horario' => ['nullable', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string'],
            'ativo' => ['boolean'],
            'motivo_afastamento' => ['nullable', 'string', 'max:255'],
        ]);

        // Ao reativar, limpa o motivo de afastamento
        if (isset($validated['ativo']) && $validated['ativo']) {
            $validated['motivo_afastamento'] = null;
        }

        $funcionario->update($validated);

        return response()->json($funcionario);
    }

    public function destroy(string $id): JsonResponse
    {
        $funcionario = Funcionario::where('id', $id)
            ->where('restaurante_id', auth('restaurante')->id())
            ->firstOrFail();

        $funcionario->delete();

        return response()->json(['message' => 'Funcionário removido.']);
    }
}
