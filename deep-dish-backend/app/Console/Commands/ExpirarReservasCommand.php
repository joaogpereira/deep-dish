<?php

namespace App\Console\Commands;

use App\Http\Controllers\ReservaController;
use Illuminate\Console\Command;

class ExpirarReservasCommand extends Command
{
    protected $signature = 'reservas:expirar';

    protected $description = 'Marca como expiradas as reservas confirmadas cujo horário + tolerância já passou e libera as mesas.';

    public function handle(): int
    {
        $total = ReservaController::expirarReservasVencidas();
        $this->info("Reservas expiradas: {$total}");

        return self::SUCCESS;
    }
}
