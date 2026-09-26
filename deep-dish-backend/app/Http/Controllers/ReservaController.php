<?php

namespace App\Http\Controllers;

use App\Events\OperacaoAtualizada;
use App\Events\ReservaAtualizada;
use App\Models\ClienteMesa;
use App\Models\Mesa;
use App\Models\Restaurante;
use App\Services\FilaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ReservaController extends Controller
{
    /**
     * Duração padrão de uma reserva (em minutos).
     * Por enquanto fixa em 1 hora — futura feature: configurável por restaurante.
     *
     * Mora no model, junto do scope que a usa para calcular sobreposição; aqui
     * fica o apelido, porque meio projeto já referencia ReservaController::.
     */
    public const DURACAO_RESERVA_MINUTOS = ClienteMesa::DURACAO_RESERVA_MINUTOS;

    /**
     * Tolerância para no-show: após esse tempo sem check-in,
     * a reserva expira automaticamente e a mesa é liberada.
     */
    private const TOLERANCIA_NO_SHOW_MINUTOS = 60;

    /** Status considerados "ativos" (bloqueia mesa no horário). */
    public const STATUS_ATIVOS = ClienteMesa::STATUS_ATIVOS;

    /**
     * Minutos de folga após o fechamento do restaurante antes de expirar sessões.
     * Dá tempo para o restaurante liberar as mesas manualmente.
     */
    private const FOLGA_POS_FECHAMENTO_MINUTOS = 60;

    /**
     * Fallback quando o restaurante não tem horário de fechamento configurado.
     */
    private const FALLBACK_SESSAO_MAXIMA_HORAS = 12;

    /**
     * Expira reservas vencidas:
     *  - 'confirmada' cujo horário + tolerância de no-show já passou.
     *  - 'em_andamento' após o horário de fechamento do restaurante + folga.
     *    Fallback de 12 h quando não há horário configurado.
     * Libera a mesa associada em ambos os casos. Retorna o total expirado.
     */
    public static function expirarReservasVencidas(): int
    {
        $limiteNoShow = Carbon::now()->subMinutes(self::TOLERANCIA_NO_SHOW_MINUTOS);

        // Reservas confirmadas sem check-in dentro da tolerância
        $noShow = ClienteMesa::where('status', 'confirmada')
            ->where('horario_reserva', '<', $limiteNoShow)
            ->get();

        // Reservas em andamento: carrega restaurante via mesa para usar horario_fechamento
        $emAndamento = ClienteMesa::where('status', 'em_andamento')
            ->with('mesa.restaurante')
            ->get();

        $sessaoExpirada = $emAndamento->filter(function (ClienteMesa $reserva) {
            $restaurante = $reserva->mesa?->restaurante;
            $fechamentoStr = $restaurante?->horario_fechamento; // "HH:MM" ou null

            $referencia = $reserva->horario_checkin ?? $reserva->horario_reserva;
            if (! $referencia) {
                return false;
            }

            if (! $fechamentoStr) {
                // Sem horário de fechamento: usa fallback de 12 horas
                return Carbon::parse($referencia)
                    ->addHours(self::FALLBACK_SESSAO_MAXIMA_HORAS)
                    ->isPast();
            }

            // Monta o datetime de fechamento do dia da reserva
            [$fh, $fm] = array_map('intval', explode(':', substr($fechamentoStr, 0, 5)));
            $dataBase = Carbon::parse($referencia)->startOfDay();
            $fechamento = $dataBase->copy()->setTime($fh, $fm);

            // Restaurantes que fecham depois da meia-noite (ex: 02:00)
            if ($fh < 12) {
                $fechamento->addDay();
            }

            return Carbon::now()->gt($fechamento->addMinutes(self::FOLGA_POS_FECHAMENTO_MINUTOS));
        });

        $vencidas = $noShow->merge($sessaoExpirada);

        if ($vencidas->isEmpty()) {
            return 0;
        }

        $restaurantesAfetados = [];

        DB::transaction(function () use ($vencidas, &$restaurantesAfetados) {
            /** @var ClienteMesa $reserva */
            foreach ($vencidas as $reserva) {
                $reserva->update(['status' => 'expirada']);
                // No-show não tem check-in, então a duração fica NULL sozinha;
                // as sessões encerradas pelo fechamento gravam duração real.
                $reserva->registrarSaida();
                $mesa = Mesa::find($reserva->mesa_id);
                if ($mesa) {
                    if (in_array($mesa->status, ['reservada', 'ocupada'])) {
                        $mesa->update(['status' => 'livre']);
                    }
                    // Chave do array: expirar 10 reservas do mesmo salão avisa o
                    // painel uma vez, não dez.
                    $restaurantesAfetados[(string) $mesa->restaurante_id] = true;
                }

                ReservaAtualizada::dispatch((string) $reserva->cliente_id);
            }
        });

        foreach (array_keys($restaurantesAfetados) as $restauranteId) {
            OperacaoAtualizada::dispatch($restauranteId);

            // Fora da transação de propósito: cada restaurante promove na sua,
            // travando só a própria linha.
            app(FilaService::class)->processarPromocoes($restauranteId);
        }

        return $vencidas->count();
    }

    // ─── Cliente: cria nova reserva (escolha direta da mesa) ─
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mesa_id' => 'required|string|uuid|exists:mesa,id',
            'party_size' => 'required|integer|min:1|max:20',
            'horario_reserva' => 'required|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Dados de entrada inválidos',
                'details' => $validator->errors(),
            ], 422);
        }

        $clienteId = auth('api')->id();
        $mesaId = $request->input('mesa_id');
        $partySize = (int) $request->input('party_size');
        $horarioInput = $request->input('horario_reserva');
        // Mantém o offset original (BRT) para validar o horário de funcionamento
        $horarioReservaBRT = Carbon::parse($horarioInput)->setTimezone('America/Sao_Paulo');
        // Converte para UTC para armazenamento e comparações de sobreposição
        $horarioReserva = Carbon::parse($horarioInput)->utc();

        try {
            return DB::transaction(function () use ($clienteId, $mesaId, $partySize, $horarioReserva, $horarioReservaBRT) {
                $mesa = Mesa::lockForUpdate()->find($mesaId);

                if (! $mesa) {
                    return response()->json(['error' => 'Mesa não encontrada.'], 404);
                }

                $restaurante = Restaurante::find($mesa->restaurante_id);
                if (! $restaurante->reservations_enabled) {
                    return response()->json(['error' => 'Este restaurante não aceita reservas.'], 422);
                }

                // Valida horário de funcionamento
                if ($restaurante->horario_abertura && $restaurante->horario_fechamento) {
                    $horaReserva = $horarioReservaBRT->format('H:i');
                    $abre = substr($restaurante->horario_abertura, 0, 5);
                    $fecha = substr($restaurante->horario_fechamento, 0, 5);
                    if ($horaReserva < $abre || $horaReserva >= $fecha) {
                        return response()->json([
                            'error' => "Este restaurante funciona das {$abre} às {$fecha}.",
                        ], 422);
                    }
                }

                if ($mesa->status === 'bloqueada') {
                    return response()->json(['error' => 'Esta mesa está indisponível.'], 422);
                }

                if ($mesa->capacidade < $partySize) {
                    return response()->json([
                        'error' => "Esta mesa comporta apenas {$mesa->capacidade} pessoas.",
                    ], 422);
                }

                // Verifica sobreposição de horário (janela de 1h)
                $fimReserva = $horarioReserva->copy()->addMinutes(self::DURACAO_RESERVA_MINUTOS);

                $conflito = ClienteMesa::where('mesa_id', $mesaId)
                    ->ativasSobrepondo($horarioReserva, $fimReserva)
                    ->exists();

                if ($conflito) {
                    return response()->json([
                        'error' => 'Esta mesa já está reservada nesse horário.',
                    ], 422);
                }

                // Cliente já tem reserva ativa nesse restaurante no mesmo horário?
                $duplicada = ClienteMesa::where('cliente_id', $clienteId)
                    ->whereHas('mesa', fn ($q) => $q->where('restaurante_id', $mesa->restaurante_id))
                    ->ativasSobrepondo($horarioReserva, $fimReserva)
                    ->exists();

                if ($duplicada) {
                    return response()->json([
                        'error' => 'Você já possui uma reserva neste restaurante nesse horário.',
                    ], 422);
                }

                // Cria a reserva (mesa permanece 'livre' até o check-in)
                $reserva = ClienteMesa::create([
                    'cliente_id' => $clienteId,
                    'mesa_id' => $mesa->id,
                    'horario_reserva' => $horarioReserva,
                    'party_size' => $partySize,
                    'status' => 'confirmada',
                ]);

                $reserva->load(['mesa.restaurante']);

                OperacaoAtualizada::dispatch((string) $mesa->restaurante_id);
                ReservaAtualizada::dispatch((string) $clienteId);

                return response()->json([
                    'message' => 'Reserva criada com sucesso! Confirme sua chegada com o restaurante para liberar sua mesa.',
                    'reserva' => $reserva,
                ], 201);
            });
        } catch (\Throwable $e) {
            Log::error('Erro ao criar reserva', ['message' => $e->getMessage()]);

            return response()->json(['error' => 'Erro interno ao criar reserva'], 500);
        }
    }

    /** Status finalizados — podem ser excluídos permanentemente. */
    private const STATUS_FINALIZADOS = ['liberada', 'expirada', 'cancelada'];

    // ─── Cliente: lista suas reservas (paginado) ─────────────
    public function index(Request $request): JsonResponse
    {
        self::expirarReservasVencidas();

        $clienteId = auth('api')->id();
        $perPage = min((int) $request->query('per_page', 10), 50);
        $grupo = $request->query('status_group'); // 'active' | 'finished' | null

        $query = ClienteMesa::with(['mesa.restaurante'])
            ->where('cliente_id', $clienteId)
            ->orderBy('horario_reserva', 'desc');

        if ($grupo === 'active') {
            $query->whereIn('status', self::STATUS_ATIVOS);
        } elseif ($grupo === 'finished') {
            $query->whereIn('status', self::STATUS_FINALIZADOS);
        }

        return response()->json($query->paginate($perPage));
    }

    // ─── Cliente: detalhe de uma reserva ────────────────────
    public function show(string $id): JsonResponse
    {
        $clienteId = auth('api')->id();

        $reserva = ClienteMesa::with(['mesa.restaurante'])
            ->where('id', $id)
            ->where('cliente_id', $clienteId)
            ->first();

        if (! $reserva) {
            return response()->json(['error' => 'Reserva não encontrada.'], 404);
        }

        return response()->json($reserva);
    }

    // ─── Cliente: cancela reserva ───────────────────────────
    public function destroy(string $id): JsonResponse
    {
        $clienteId = auth('api')->id();

        $reserva = ClienteMesa::where('id', $id)
            ->where('cliente_id', $clienteId)
            ->first();

        if (! $reserva) {
            return response()->json(['error' => 'Reserva não encontrada.'], 404);
        }

        if (! in_array($reserva->status, self::STATUS_ATIVOS)) {
            return response()->json(['error' => 'Esta reserva já foi finalizada.'], 422);
        }

        $reserva->update(['status' => 'cancelada']);
        // Cancelamento depois do check-in é permanência real; antes dele fica NULL.
        $reserva->registrarSaida();

        // Libera a mesa se estiver ocupada (check-in já havia sido feito)
        $mesa = Mesa::find($reserva->mesa_id);
        if ($mesa && $mesa->status === 'ocupada') {
            $mesa->update(['status' => 'livre']);
        }

        if ($mesa) {
            OperacaoAtualizada::dispatch((string) $mesa->restaurante_id);

            // Cancelar libera lugar: com check-in, a mesa em si; sem ele, a
            // janela de horário que a reserva segurava. Nos dois casos a fila anda.
            app(FilaService::class)->processarPromocoes((string) $mesa->restaurante_id);
        }
        ReservaAtualizada::dispatch((string) $clienteId);

        return response()->json([
            'message' => 'Reserva cancelada.',
            'reserva' => $reserva->fresh(['mesa.restaurante']),
        ]);
    }

    // ─── Restaurante: lista reservas das suas mesas (paginado)
    public function indexRestaurante(Request $request): JsonResponse
    {
        self::expirarReservasVencidas();

        $restauranteId = auth('restaurante')->id();
        $perPage = min((int) $request->query('per_page', 10), 50);
        $grupo = $request->query('status_group'); // 'active' | 'finished' | null

        $statusAtivos = implode("','", self::STATUS_ATIVOS);

        $query = ClienteMesa::with(['mesa', 'cliente'])
            ->whereHas('mesa', fn ($q) => $q->where('restaurante_id', $restauranteId))
            ->orderByRaw("CASE WHEN status IN ('{$statusAtivos}') THEN 0 ELSE 1 END")
            ->orderBy('horario_reserva', 'desc');

        if ($grupo === 'active') {
            $query->whereIn('status', self::STATUS_ATIVOS);
        } elseif ($grupo === 'finished') {
            $query->whereIn('status', self::STATUS_FINALIZADOS);
        }

        return response()->json($query->paginate($perPage));
    }

    // ─── Restaurante: faz check-in do cliente ───────────────
    public function checkin(string $id): JsonResponse
    {
        $restauranteId = auth('restaurante')->id();

        $reserva = ClienteMesa::with('mesa')
            ->where('id', $id)
            ->whereHas('mesa', fn ($q) => $q->where('restaurante_id', $restauranteId))
            ->first();

        if (! $reserva) {
            return response()->json(['error' => 'Reserva não encontrada.'], 404);
        }

        if ($reserva->status !== 'confirmada') {
            return response()->json(['error' => 'Só é possível fazer check-in de reservas confirmadas.'], 422);
        }

        $reserva->update([
            'status' => 'em_andamento',
            'horario_checkin' => now(),
        ]);

        // Mesa passa para ocupada (estava livre, pois reserva não bloqueia status)
        $mesa = $reserva->mesa;
        if ($mesa && $mesa->status !== 'bloqueada') {
            $mesa->update(['status' => 'ocupada']);
        }

        OperacaoAtualizada::dispatch((string) $restauranteId);
        ReservaAtualizada::dispatch((string) $reserva->cliente_id);

        return response()->json([
            'message' => 'Check-in realizado! Mesa liberada para o cliente.',
            'reserva' => $reserva->fresh(['mesa', 'cliente']),
        ]);
    }

    // ─── Restaurante: marca mesa como liberada ──────────────
    public function liberar(string $id): JsonResponse
    {
        $restauranteId = auth('restaurante')->id();

        $reserva = ClienteMesa::with('mesa')
            ->where('id', $id)
            ->whereHas('mesa', fn ($q) => $q->where('restaurante_id', $restauranteId))
            ->first();

        if (! $reserva) {
            return response()->json(['error' => 'Reserva não encontrada.'], 404);
        }

        if (! in_array($reserva->status, self::STATUS_ATIVOS)) {
            return response()->json(['error' => 'Esta reserva já foi finalizada.'], 422);
        }

        $reserva->update(['status' => 'liberada']);
        $reserva->registrarSaida();

        // Mesa volta a ficar livre
        $mesa = $reserva->mesa;
        if ($mesa) {
            $mesa->update(['status' => 'livre']);

            app(FilaService::class)->processarPromocoes((string) $mesa->restaurante_id);
        }

        OperacaoAtualizada::dispatch((string) $restauranteId);
        ReservaAtualizada::dispatch((string) $reserva->cliente_id);

        return response()->json([
            'message' => 'Mesa liberada.',
            'reserva' => $reserva->fresh(['mesa', 'cliente']),
        ]);
    }

    // ─── Restaurante: remove reserva finalizada do painel ───
    // Soft delete: a linha sai das listagens mas o histórico de permanência
    // continua no banco, acessível ao Analytics via withTrashed().
    public function forceDestroyRestaurante(string $id): JsonResponse
    {
        $restauranteId = auth('restaurante')->id();

        $reserva = ClienteMesa::where('id', $id)
            ->whereHas('mesa', fn ($q) => $q->where('restaurante_id', $restauranteId))
            ->first();

        if (! $reserva) {
            return response()->json(['error' => 'Reserva não encontrada.'], 404);
        }

        if (! in_array($reserva->status, self::STATUS_FINALIZADOS)) {
            return response()->json(['error' => 'Só é possível excluir reservas finalizadas (liberada, expirada ou cancelada).'], 422);
        }

        $reserva->delete();

        OperacaoAtualizada::dispatch((string) $restauranteId);

        return response()->json(['message' => 'Reserva excluída.']);
    }
}
