<?php

namespace Tests\Feature;

use App\Models\ClienteMesa;
use App\Models\Mesa;
use App\Models\Restaurante;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Exceptions;
use Tests\TestCase;

/**
 * Reverb fora do ar nao derruba a aplicacao (criterio de aceite da Sprint 3).
 *
 * Aponta o driver reverb para uma porta onde ninguem escuta. O phpunit.xml ja
 * roda com QUEUE_CONNECTION=sync, que e justamente o caso em que o envio
 * acontece dentro da requisicao.
 */
class ReverbForaDoArTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'chave-de-teste',
            'broadcasting.connections.reverb.secret' => 'segredo-de-teste',
            'broadcasting.connections.reverb.app_id' => 'app-de-teste',
            'broadcasting.connections.reverb.options.host' => '127.0.0.1',
            'broadcasting.connections.reverb.options.port' => 1,
            'broadcasting.connections.reverb.options.scheme' => 'http',
            'broadcasting.connections.reverb.options.useTLS' => false,
        ]);
        Broadcast::purge();
    }

    public function test_liberar_mesa_com_fila_sync_responde_ok_e_registra_a_falha(): void
    {
        Exceptions::fake();

        $restaurante = Restaurante::factory()->create();
        $mesa = Mesa::factory()->ocupada()->for($restaurante)->create();
        $reserva = ClienteMesa::factory()->emAndamento()->for($mesa)->create();

        $this->withHeader('Authorization', 'Bearer '.auth('restaurante')->login($restaurante))
            ->patchJson("/api/restaurante/reservas/{$reserva->id}/liberar")
            ->assertOk();

        $this->assertSame('liberada', $reserva->fresh()->status);
        $this->assertSame('livre', $mesa->fresh()->status);
        Exceptions::assertReported(BroadcastException::class);
    }

    public function test_fora_da_fila_sync_a_falha_sobe_para_o_worker_tentar_de_novo(): void
    {
        config(['queue.default' => 'database']);

        $this->expectException(BroadcastException::class);

        Broadcast::driver()->broadcast(['private-restaurante.1'], 'operacao.atualizada');
    }
}
