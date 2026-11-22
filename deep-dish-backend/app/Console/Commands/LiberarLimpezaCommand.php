<?php

namespace App\Console\Commands;

use App\Services\MesaLimpezaService;
use Illuminate\Console\Command;

class LiberarLimpezaCommand extends Command
{
    protected $signature = 'mesas:liberar-limpeza';

    protected $description = 'Devolve para livre a mesa que passou da tolerância em limpeza, e manda a fila andar.';

    public function handle(MesaLimpezaService $mesaLimpezaService): int
    {
        $total = $mesaLimpezaService->liberarVencidas();

        $this->info("Mesas liberadas: {$total}");

        return self::SUCCESS;
    }
}
