<?php

namespace App\Console\Commands;

use App\Models\Fila;
use App\Models\Restaurante;
use App\Services\FilaService;
use Illuminate\Console\Command;

/**
 * Rede de segurança do ponto único de promoção.
 *
 * Os caminhos que liberam lugar já chamam o FilaService na hora. Este comando
 * cobre o que não é evento de mesa: entrar na fila quando já há mesa vaga, uma
 * janela de reserva que venceu sozinha no relógio, ou qualquer caminho novo que
 * alguém esqueça de plugar. Roda a cada minuto e não faz nada quando não há
 * fila esperando.
 */
class PromoverFilasCommand extends Command
{
    protected $signature = 'fila:promover';

    protected $description = 'Chama da fila quem couber nas mesas disponíveis de cada restaurante com fila ativa.';

    public function handle(FilaService $filaService): int
    {
        $restaurantes = Restaurante::query()
            ->where('fila_ativa', true)
            ->whereHas('clienteFilas', fn ($q) => $q
                ->ativas()
                ->whereHas('fila', fn ($f) => $f->where('status', Fila::STATUS_ABERTA))
            )
            ->pluck('id');

        $promovidos = 0;

        foreach ($restaurantes as $restauranteId) {
            $promovidos += $filaService->processarPromocoes((string) $restauranteId)->count();
        }

        $this->info("Clientes chamados: {$promovidos}");

        return self::SUCCESS;
    }
}
