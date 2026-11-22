<?php

namespace App\Http\Controllers;

use App\Models\ClienteFila;
use App\Models\ClienteMesa;
use App\Models\Fila;
use App\Models\Mesa;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $restauranteId = auth('restaurante')->id();

        $queueSize = ClienteFila::query()
            ->ativas()
            ->whereHas('fila', fn ($q) => $q
                ->where('restaurante_id', $restauranteId)
                ->where('status', Fila::STATUS_ABERTA)
            )
            ->count();

        $reservationsToday = ClienteMesa::whereHas('mesa', fn ($q) => $q
            ->where('restaurante_id', $restauranteId)
        )
            ->whereDate('horario_reserva', Carbon::today())
            ->whereNotIn('status', ['cancelada', 'liberada', 'expirada'])
            ->count();

        $totalTables = Mesa::where('restaurante_id', $restauranteId)->count();
        $tablesAvailable = Mesa::where('restaurante_id', $restauranteId)
            ->where('status', 'livre')
            ->count();

        // Toda mesa que nao esta livre conta como ocupada (reservada, ocupada,
        // bloqueada). Sem mesa cadastrada nao ha taxa: devolve null para o
        // frontend mostrar '—' em vez de 0% ou NaN.
        $occupancyPercent = $totalTables > 0
            ? (int) round(($totalTables - $tablesAvailable) / $totalTables * 100)
            : null;

        return response()->json([
            'queue_size' => $queueSize,
            'reservations_today' => $reservationsToday,
            'tables_available' => $tablesAvailable,
            'total_tables' => $totalTables,
            'occupancy_percent' => $occupancyPercent,
        ]);
    }
}
