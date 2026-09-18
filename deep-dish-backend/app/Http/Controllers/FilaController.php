<?php

namespace App\Http\Controllers;

use App\Events\FilaAtualizada;
use App\Events\PosicaoFilaAtualizada;
use App\Models\ClienteFila;
use App\Models\Fila;
use App\Services\EstimativaEsperaService;
use App\Services\FilaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class FilaController extends Controller
{
    public function __construct(
        private FilaService $filaService,
        private EstimativaEsperaService $estimativaService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'restaurante_id' => ['required', 'string', 'uuid', 'exists:restaurante,id'],
            'horario_reserva' => ['required', 'date', 'after:now'],
            'qntd_pessoas' => ['required', 'integer', 'min:1'],
        ]);

        $horarioReserva = Carbon::parse($validated['horario_reserva'])->utc();
        $horarioUTC = $horarioReserva->format('Y-m-d H:i:s');

        try {
            $clienteFila = $this->filaService->enfileirar(
                (string) auth('api')->id(),
                $validated['restaurante_id'],
                $horarioUTC,
                (int) $validated['qntd_pessoas']
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $clienteFila->load('fila')->append('posicao');

        $estimativa = $this->estimativaService->estimar(
            $validated['restaurante_id'],
            (int) $clienteFila->posicao,
            (int) $validated['qntd_pessoas'],
            $horarioReserva
        );

        $data = array_merge($clienteFila->toArray(), $estimativa);

        return response()->json([
            'message' => 'Você está na posição '.$clienteFila->posicao.' da fila.',
            'data' => $data,
        ], 201);
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->filaService->cancelarPosicao($id, (string) auth('api')->id());
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 403);
        }

        return response()->json([
            'message' => 'Posição na fila cancelada com sucesso.',
        ]);
    }

    // ─── Restaurante: lista entradas na fila ────────────────
    public function indexRestaurante(): JsonResponse
    {
        $restauranteId = auth('restaurante')->id();

        $entries = ClienteFila::with(['fila', 'cliente'])
            ->ativas()
            ->whereHas('fila', fn ($q) => $q
                ->where('restaurante_id', $restauranteId)
                ->where('status', Fila::STATUS_ABERTA)
            )
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        // Posição calculada em memória: a lista já está ordenada e contém só ativos.
        // Usar ->append('posicao') aqui dispararia um COUNT por registro.
        $entries->groupBy('fila_id')->each(function ($daFila) use ($restauranteId) {
            $daFila->values()->each(function ($registro, $i) use ($restauranteId) {
                $posicao = $i + 1;
                $registro->setAttribute('posicao', $posicao);

                $estimativa = $this->estimativaService->estimar(
                    (string) $restauranteId,
                    $posicao,
                    (int) $registro->qntd_pessoas,
                    $registro->fila->horario_reserva
                );

                foreach ($estimativa as $campo => $valor) {
                    $registro->setAttribute($campo, $valor);
                }
            });
        });

        return response()->json($entries);
    }

    // ─── Restaurante: remove entrada da fila ────────────────
    public function removerRestaurante(string $id): JsonResponse
    {
        $restauranteId = auth('restaurante')->id();

        $registro = ClienteFila::query()
            ->ativas()
            ->whereKey($id)
            ->whereHas('fila', fn ($q) => $q->where('restaurante_id', $restauranteId))
            ->first();

        if (! $registro) {
            return response()->json(['message' => 'Entrada não encontrada.'], 404);
        }

        $fila = $registro->fila;

        $registro->registrarSaida(ClienteFila::STATUS_SAIDA_REMOVIDO);

        $this->filaService->encerrarFilaSeVazia($fila);

        FilaAtualizada::dispatch((string) $restauranteId);
        PosicaoFilaAtualizada::dispatch((string) $fila->id);

        return response()->json(['message' => 'Removido da fila.']);
    }

    public function consultarPosicao(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'restaurante_id' => ['required', 'string', 'uuid', 'exists:restaurante,id'],
            'horario_reserva' => ['required', 'date'],
        ]);

        $horarioReserva = Carbon::parse($validated['horario_reserva'])->utc();
        $horarioUTC = $horarioReserva->format('Y-m-d H:i:s');

        $registro = $this->filaService->consultarPosicao(
            (string) auth('api')->id(),
            $validated['restaurante_id'],
            $horarioUTC
        );

        if (! $registro) {
            return response()->json([
                'message' => 'Você não está na fila para este horário.',
                'status_saida' => $this->filaService->statusSaida(
                    (string) auth('api')->id(),
                    $validated['restaurante_id'],
                    $horarioUTC
                ),
            ], 404);
        }

        $registro->load('fila');

        $estimativa = $this->estimativaService->estimar(
            $validated['restaurante_id'],
            (int) $registro->posicao,
            (int) $registro->qntd_pessoas,
            $horarioReserva
        );

        $data = array_merge($registro->toArray(), $estimativa);

        return response()->json($data);
    }

    // ─── Cliente: estimativa antes de entrar na fila ────────
    public function estimativa(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'restaurante_id' => ['required', 'string', 'uuid', 'exists:restaurante,id'],
            'horario_reserva' => ['required', 'date'],
            'qntd_pessoas' => ['required', 'integer', 'min:1'],
        ]);

        $horarioReserva = Carbon::parse($validated['horario_reserva'])->utc();
        $horarioUTC = $horarioReserva->format('Y-m-d H:i:s');

        // Posição hipotética: quem entrasse agora ficaria depois de todo mundo já ativo.
        $posicaoEstimada = $this->filaService->contarAtivos($validated['restaurante_id'], $horarioUTC) + 1;

        $estimativa = $this->estimativaService->estimar(
            $validated['restaurante_id'],
            $posicaoEstimada,
            (int) $validated['qntd_pessoas'],
            $horarioReserva
        );

        return response()->json($estimativa);
    }
}
