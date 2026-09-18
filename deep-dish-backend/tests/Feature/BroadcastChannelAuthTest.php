<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ClienteFila;
use App\Models\Fila;
use App\Models\Restaurante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POST /api/broadcasting/auth — autorizacao dos canais privados (#172).
 *
 * O phpunit.xml usa BROADCAST_CONNECTION=null, e o NullBroadcaster aprova
 * qualquer canal sem rodar os callbacks do routes/channels.php. Por isso o
 * teste troca para o driver reverb e registra os canais de novo nele: e o
 * unico jeito de exercitar as regras de verdade. Nada e transmitido — o
 * /broadcasting/auth so assina o socket_id localmente com o secret.
 */
class BroadcastChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    private const ROTA = '/api/broadcasting/auth';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'testkey',
            'broadcasting.connections.reverb.secret' => 'testsecret',
            'broadcasting.connections.reverb.app_id' => 'testapp',
        ]);

        require base_path('routes/channels.php');
    }

    public function test_cliente_assina_o_proprio_canal(): void
    {
        $cliente = Cliente::factory()->create();

        $this->autorizar($this->tokenCliente($cliente), "cliente.{$cliente->id}")
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_cliente_nao_assina_canal_de_outro_cliente(): void
    {
        $cliente = Cliente::factory()->create();
        $outro = Cliente::factory()->create();

        $this->autorizar($this->tokenCliente($cliente), "cliente.{$outro->id}")
            ->assertForbidden();
    }

    public function test_restaurante_assina_o_proprio_canal(): void
    {
        $restaurante = Restaurante::factory()->create();

        $this->autorizar($this->tokenRestaurante($restaurante), "restaurante.{$restaurante->id}")
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_restaurante_nao_assina_canal_de_outro_restaurante(): void
    {
        $restaurante = Restaurante::factory()->create();
        $outro = Restaurante::factory()->create();

        $this->autorizar($this->tokenRestaurante($restaurante), "restaurante.{$outro->id}")
            ->assertForbidden();
    }

    public function test_cliente_nao_assina_canal_de_restaurante(): void
    {
        $cliente = Cliente::factory()->create();

        $this->autorizar($this->tokenCliente($cliente), 'restaurante.'.Restaurante::factory()->create()->id)
            ->assertForbidden();
    }

    public function test_restaurante_nao_assina_canal_de_cliente(): void
    {
        $restaurante = Restaurante::factory()->create();

        $this->autorizar($this->tokenRestaurante($restaurante), 'cliente.'.Cliente::factory()->create()->id)
            ->assertForbidden();
    }

    public function test_cliente_ativo_na_fila_assina_o_canal_da_fila(): void
    {
        $entrada = ClienteFila::factory()->create();

        $this->autorizar($this->tokenCliente($entrada->cliente), "fila.{$entrada->fila_id}")
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_cliente_nao_assina_fila_em_que_nao_esta(): void
    {
        $cliente = Cliente::factory()->create();
        $fila = Fila::factory()->create();
        ClienteFila::factory()->for($fila)->create(); // a fila tem gente, so nao ele

        $this->autorizar($this->tokenCliente($cliente), "fila.{$fila->id}")
            ->assertForbidden();
    }

    public function test_cliente_que_ja_saiu_da_fila_nao_assina_mais(): void
    {
        $entrada = ClienteFila::factory()->desistiu()->create();

        $this->autorizar($this->tokenCliente($entrada->cliente), "fila.{$entrada->fila_id}")
            ->assertForbidden();
    }

    public function test_sem_token_nao_autentica(): void
    {
        $this->postJson(self::ROTA, [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-cliente.'.Cliente::factory()->create()->id,
        ])->assertUnauthorized();
    }

    private function autorizar(string $token, string $canal): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson(self::ROTA, [
                'socket_id' => '1234.5678',
                'channel_name' => "private-{$canal}",
            ]);
    }

    private function tokenCliente(Cliente $cliente): string
    {
        return auth('api')->login($cliente);
    }

    private function tokenRestaurante(Restaurante $restaurante): string
    {
        return auth('restaurante')->login($restaurante);
    }
}
