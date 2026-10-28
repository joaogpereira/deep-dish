<?php

namespace App\Http\Controllers;

use App\Events\OperacaoAtualizada;
use App\Models\ClienteMesa;
use App\Models\Mesa;
use App\Services\FilaService;
use App\Services\MesaLimpezaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MesaController extends Controller
{
    /**
     * Para onde cada status pode ir pelo painel (#171).
     *
     * 'aguardando_limpeza' nao e destino de ninguem: quem o escreve e o
     * MesaLimpezaService, quando o cliente sai. O painel so empurra o ciclo para
     * frente a partir dele.
     *
     * 'livre' direto de 'aguardando_limpeza' e o escape "ja estava limpa"; nesse
     * caso nao ha duracao de limpeza para cronometrar, e o ciclo fecha sem ela.
     */
    private const TRANSICOES = [
        Mesa::STATUS_LIVRE => [Mesa::STATUS_BLOQUEADA],
        Mesa::STATUS_OCUPADA => [Mesa::STATUS_BLOQUEADA],
        Mesa::STATUS_AGUARDANDO_LIMPEZA => [Mesa::STATUS_EM_LIMPEZA, Mesa::STATUS_LIVRE, Mesa::STATUS_BLOQUEADA],
        Mesa::STATUS_EM_LIMPEZA => [Mesa::STATUS_LIVRE, Mesa::STATUS_BLOQUEADA],
        Mesa::STATUS_BLOQUEADA => [Mesa::STATUS_LIVRE],
    ];

    // ─── Rota pública: mesas disponíveis de um restaurante ──
    // Query params opcionais:
    //   horario      = ISO datetime (ex: 2026-04-13T19:00:00) → filtra por sobreposição de reservas
    //   capacidade_min = int → filtra por capacidade mínima
    public function disponiveis(Request $request, string $id): JsonResponse
    {
        $horarioParam = $request->query('horario');

        // 'bloqueada' é sempre excluída; 'ocupada' reflete estado atual e não deve
        // bloquear datas futuras — a sobreposição de reservas cuida disso abaixo.
        $query = Mesa::where('restaurante_id', $id)
            ->where('status', '!=', 'bloqueada')
            ->orderBy('capacidade', 'asc');

        if ($request->has('capacidade_min')) {
            $query->where('capacidade', '>=', (int) $request->query('capacidade_min'));
        }

        if ($horarioParam) {
            $inicio = \Carbon\Carbon::parse($horarioParam)->utc();
            $fim = $inicio->copy()->addMinutes(ReservaController::DURACAO_RESERVA_MINUTOS);

            // Exclui apenas mesas com reserva ativa que se sobreponha à janela pedida.
            $conflitantes = ClienteMesa::query()
                ->ativasSobrepondo($inicio, $fim)
                ->pluck('mesa_id');

            $query->whereNotIn('id', $conflitantes);
        } else {
            // Sem horário: comportamento legado — só mesas com status livre agora
            $query->where('status', 'livre');
        }

        return response()->json($query->get());
    }

    // ─── Lista mesas do restaurante logado ──────────────────
    public function index(): JsonResponse
    {
        $restauranteId = auth('restaurante')->id();

        $mesas = Mesa::where('restaurante_id', $restauranteId)
            ->orderBy('numero', 'asc')
            ->get();

        return response()->json($mesas);
    }

    // ─── Cria nova mesa ─────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $restauranteId = auth('restaurante')->id();

        $validator = Validator::make($request->all(), [
            'numero' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('mesa', 'numero')->where('restaurante_id', $restauranteId),
            ],
            'capacidade' => 'required|integer|min:1|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Dados inválidos',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $mesa = Mesa::create([
                'restaurante_id' => $restauranteId,
                'numero' => $request->input('numero'),
                'capacidade' => $request->input('capacidade'),
                'status' => 'livre',
                'confirmacao' => 'pendente',
            ]);

            OperacaoAtualizada::dispatch((string) $restauranteId);

            // Mesa nova é lugar novo: quem está esperando pode caber nela.
            app(FilaService::class)->processarPromocoes((string) $restauranteId);

            return response()->json([
                'message' => 'Mesa criada com sucesso!',
                'mesa' => $mesa,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Erro ao criar mesa', ['message' => $e->getMessage()]);

            return response()->json(['error' => 'Erro interno ao criar mesa'], 500);
        }
    }

    // ─── Atualiza mesa ──────────────────────────────────────
    public function update(Request $request, string $id): JsonResponse
    {
        $restauranteId = auth('restaurante')->id();

        $mesa = Mesa::where('id', $id)
            ->where('restaurante_id', $restauranteId)
            ->first();

        if (! $mesa) {
            return response()->json(['error' => 'Mesa não encontrada.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'numero' => [
                'sometimes',
                'integer',
                'min:1',
                Rule::unique('mesa', 'numero')
                    ->where('restaurante_id', $restauranteId)
                    ->ignore($mesa->id),
            ],
            'capacidade' => 'sometimes|integer|min:1|max:30',
            'status' => 'sometimes|string|in:livre,bloqueada,aguardando_limpeza,em_limpeza',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Dados inválidos',
                'details' => $validator->errors(),
            ], 422);
        }

        $novoStatus = $request->input('status');

        if ($novoStatus !== null && $novoStatus !== $mesa->status) {
            if (! in_array($novoStatus, self::TRANSICOES[$mesa->status] ?? [], true)) {
                return response()->json([
                    'error' => "Não é possível mudar esta mesa de '{$mesa->status}' para '{$novoStatus}'.",
                ], 422);
            }

            // O guard de reserva ativa existe para impedir que a mesa SAIA DE
            // OPERAÇÃO com uma reserva viva marcada nela. Aplicá-lo ao ciclo de
            // limpeza criava um impasse: a mesa em limpeza continua sendo oferecida
            // para reserva futura (disponiveis() só exclui 'bloqueada', e isso está
            // certo — limpar agora não impede reservar para as 20h), então bastava
            // alguém reservar para a noite e o restaurante não conseguia mais
            // concluir a limpeza. A mesa só sairia pela liberação automática.
            $envolveBloqueio = $novoStatus === Mesa::STATUS_BLOQUEADA
                || $mesa->status === Mesa::STATUS_BLOQUEADA;

            if ($envolveBloqueio) {
                $temReservaAtiva = ClienteMesa::where('mesa_id', $mesa->id)
                    ->whereIn('status', ReservaController::STATUS_ATIVOS)
                    ->exists();

                if ($temReservaAtiva) {
                    return response()->json([
                        'error' => 'Não é possível alterar o status desta mesa pois há uma reserva ativa vinculada a ela.',
                    ], 422);
                }
            }
        }

        try {
            $dados = $validator->validated();
            unset($dados['status']);

            if ($dados !== []) {
                $mesa->update($dados);
            }

            // O status das transições de limpeza é escrito pelo MesaLimpezaService,
            // nunca aqui: é ele que mantém a linha de 'mesa_limpeza' em dia com o
            // estado da mesa.
            if ($novoStatus !== null && $novoStatus !== $mesa->status) {
                $this->aplicarStatus($mesa, $novoStatus);
            }

            OperacaoAtualizada::dispatch((string) $restauranteId);

            // Desbloquear devolve a mesa ao salão, e aumentar a capacidade pode
            // fazer caber quem antes não cabia.
            if ($request->hasAny(['status', 'capacidade'])) {
                app(FilaService::class)->processarPromocoes((string) $restauranteId);
            }

            return response()->json([
                'message' => 'Mesa atualizada!',
                'mesa' => $mesa->fresh(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Erro ao atualizar mesa', ['message' => $e->getMessage()]);

            return response()->json(['error' => 'Erro interno ao atualizar mesa'], 500);
        }
    }

    /**
     * Escreve o status novo pelo caminho certo.
     *
     * As transições do ciclo de limpeza passam pelo MesaLimpezaService porque ele
     * também mantém a linha de 'mesa_limpeza': status da mesa e histórico mudam
     * juntos, na mesma transação. O resto (bloquear, desbloquear) é escrita direta.
     *
     * A transição já foi validada contra self::TRANSICOES no update().
     */
    private function aplicarStatus(Mesa $mesa, string $novoStatus): void
    {
        $limpeza = app(MesaLimpezaService::class);

        if ($novoStatus === Mesa::STATUS_EM_LIMPEZA) {
            $limpeza->iniciarLimpeza($mesa);

            return;
        }

        if ($novoStatus === Mesa::STATUS_LIVRE && in_array($mesa->status, Mesa::STATUS_LIMPEZA, true)) {
            $limpeza->concluirLimpeza($mesa);

            return;
        }

        $mesa->update(['status' => $novoStatus]);
    }

    // ─── Remove mesa ────────────────────────────────────────
    public function destroy(string $id): JsonResponse
    {
        $restauranteId = auth('restaurante')->id();

        $mesa = Mesa::where('id', $id)
            ->where('restaurante_id', $restauranteId)
            ->first();

        if (! $mesa) {
            return response()->json(['error' => 'Mesa não encontrada.'], 404);
        }

        $temReservaAtiva = ClienteMesa::where('mesa_id', $mesa->id)
            ->whereIn('status', ReservaController::STATUS_ATIVOS)
            ->exists();

        if ($temReservaAtiva) {
            return response()->json([
                'error' => 'Não é possível remover esta mesa pois há uma reserva ativa vinculada a ela.',
            ], 422);
        }

        $mesa->delete();

        OperacaoAtualizada::dispatch((string) $restauranteId);

        return response()->json(['message' => 'Mesa removida.']);
    }
}
