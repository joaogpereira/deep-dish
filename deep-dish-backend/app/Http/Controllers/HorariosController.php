<?php

namespace App\Http\Controllers;

use App\Models\Fila;
use App\Models\Restaurante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class HorariosController extends Controller
{
    /**
     * Exibe os horários configurados pelo restaurante para reservas e entrada na fila.
     * Dados obtidos das tabelas clientemesa e clientefila.
     */
    public function show(Request $request, int|string|null $restaurante = null): JsonResponse
    {
        try {
            $id = $restaurante ?? $request->query('restaurante_id');
            if ($id === null || $id === '') {
                return response()->json([
                    'message' => 'O parâmetro restaurante_id é obrigatório.',
                ], 422);
            }

            $tipo = $request->query('tipo');
            if ($tipo !== null && ! in_array($tipo, ['reservas', 'fila'], true)) {
                return response()->json([
                    'message' => 'O parâmetro tipo deve ser "reservas" ou "fila".',
                ], 422);
            }

            $restaurante = Restaurante::query()->find((string) $id);

            if (! $restaurante) {
                return response()->json([
                    'message' => 'Restaurante não encontrado.',
                ], 404);
            }

            $relations = [];
            if ($tipo === null || $tipo === 'reservas') {
                $relations['mesas.clienteMesas'] = fn ($q) => $q;
            }
            if ($tipo === null || $tipo === 'fila') {
                $relations['filas'] = fn ($q) => $q->where('status', Fila::STATUS_ABERTA);
                $relations['filas.clienteFilas'] = fn ($q) => $q
                    ->ativas()
                    ->orderBy('created_at')
                    ->orderBy('id');
            }

            if ($relations !== []) {
                $restaurante->load($relations);
            }

            $horariosReservas = [];
            if ($tipo === null || $tipo === 'reservas') {
                foreach ($restaurante->mesas as $mesa) {
                    foreach ($mesa->clienteMesas as $clienteMesa) {
                        $horariosReservas[] = [
                            'horario_reserva' => $clienteMesa->horario_reserva?->format(\DateTimeInterface::ATOM),
                            'status' => $clienteMesa->status,
                            'mesa_id' => $mesa->id,
                        ];
                    }
                }
            }

            $horariosFila = [];
            if ($tipo === null || $tipo === 'fila') {
                // Posição única do restaurante, na ordem de chegada entre todas as
                // filas abertas — a mesma de ClienteFila::getPosicaoAttribute.
                $entradas = $restaurante->filas
                    ->flatMap(fn ($fila) => $fila->clienteFilas->each->setRelation('fila', $fila))
                    ->sortBy([['created_at', 'asc'], ['id', 'asc']])
                    ->values();

                foreach ($entradas as $i => $clienteFila) {
                    $horariosFila[] = [
                        'horario_reserva' => $clienteFila->fila->horario_reserva?->format(\DateTimeInterface::ATOM),
                        'horario_entrada' => $clienteFila->created_at->format(\DateTimeInterface::ATOM),
                        'qntd_pessoas' => $clienteFila->qntd_pessoas,
                        'posicao' => $i + 1,
                        'fila_id' => $clienteFila->fila_id,
                    ];
                }
            }

            $data = [
                'restaurante_id' => $restaurante->id,
                'nome' => $restaurante->name,
                'horario_abertura' => $restaurante->horario_abertura,
                'horario_fechamento' => $restaurante->horario_fechamento,
                'fila_ativa' => (bool) $restaurante->fila_ativa,
            ];

            if ($tipo === null || $tipo === 'reservas') {
                $data['reservas'] = $horariosReservas;
            }

            if ($tipo === null || $tipo === 'fila') {
                $data['fila'] = $horariosFila;
            }

            return response()->json($data);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Erro ao carregar horários.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
