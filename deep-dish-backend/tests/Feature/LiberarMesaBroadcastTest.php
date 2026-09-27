<?php

namespace Tests\Feature;

use App\Events\OperacaoAtualizada;
use App\Events\ReservaAtualizada;
use App\Models\ClienteMesa;
use App\Models\Mesa;
use App\Models\Restaurante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * PATCH /api/restaurante/reservas/{id}/liberar — o aviso em tempo real.
 *
 * O cliente com a tela aberta so descobre que a mesa foi liberada pelo
 * ReservaAtualizada no canal pessoal dele; o painel do restaurante, pelo
 * OperacaoAtualizada no canal do salao.
 */
class LiberarMesaBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_liberar_mesa_avisa_o_cliente_da_reserva_e_o_salao(): void
    {
        Event::fake([ReservaAtualizada::class, OperacaoAtualizada::class]);

        $restaurante = Restaurante::factory()->create();
        $mesa = Mesa::factory()->ocupada()->for($restaurante)->create();
        $reserva = ClienteMesa::factory()->emAndamento()->for($mesa)->create();

        $this->comToken($restaurante)
            ->patchJson("/api/restaurante/reservas/{$reserva->id}/liberar")
            ->assertOk();

        Event::assertDispatched(
            ReservaAtualizada::class,
            fn (ReservaAtualizada $e) => $e->clienteId === (string) $reserva->cliente_id
                && $e->broadcastOn()->name === 'private-cliente.'.$reserva->cliente_id
        );
        Event::assertDispatched(
            OperacaoAtualizada::class,
            fn (OperacaoAtualizada $e) => $e->restauranteId === (string) $restaurante->id
        );
    }

    public function test_liberar_reserva_de_outro_restaurante_nao_avisa_ninguem(): void
    {
        Event::fake([ReservaAtualizada::class, OperacaoAtualizada::class]);

        $dono = Restaurante::factory()->create();
        $mesa = Mesa::factory()->ocupada()->for($dono)->create();
        $reserva = ClienteMesa::factory()->emAndamento()->for($mesa)->create();

        $this->comToken(Restaurante::factory()->create())
            ->patchJson("/api/restaurante/reservas/{$reserva->id}/liberar")
            ->assertNotFound();

        Event::assertNotDispatched(ReservaAtualizada::class);
        Event::assertNotDispatched(OperacaoAtualizada::class);
    }

    /** @return $this */
    private function comToken(Restaurante $restaurante): static
    {
        return $this->withHeader('Authorization', 'Bearer '.auth('restaurante')->login($restaurante));
    }
}
