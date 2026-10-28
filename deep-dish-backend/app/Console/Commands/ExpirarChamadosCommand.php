<?php

namespace App\Console\Commands;

use App\Services\FilaService;
use Illuminate\Console\Command;

class ExpirarChamadosCommand extends Command
{
    protected $signature = 'fila:expirar-chamados';

    protected $description = 'Marca como expirado quem foi chamado para a mesa e não fez check-in dentro da tolerância, e passa a mesa para o próximo da fila.';

    public function handle(FilaService $filaService): int
    {
        $total = $filaService->expirarChamadosSemConfirmacao();
        $this->info("Chamados expirados: {$total}");

        return self::SUCCESS;
    }
}
