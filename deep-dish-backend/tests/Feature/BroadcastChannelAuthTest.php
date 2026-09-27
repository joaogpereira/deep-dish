<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ClienteFila;
use App\Models\Restaurante;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * POST /api/broadcasting/auth — quem pode assinar qual canal privado.
 *
 * O phpunit.xml usa BROADCAST_CONNECTION=null, e o NullBroadcaster aprova
 * qualquer inscricao sem consultar routes/channels.php. Por isso o setUp troca
 * para o driver reverb (so assina a resposta localmente, nao abre conexao com
 * servidor nenhum) e registra os canais de novo nesse driver.
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
            'broadcasting.connections.reverb.key' => 'chave-de-teste',
            'broadcasting.connections.reverb.secret' => 'segredo-de-teste',
            'broadcasting.connections.reverb.app_id' => 'app-de-teste',
        ]);

        // Os canais foram registrados no boot, no driver null. O driver novo
        // precisa recebe-los, senao toda inscricao seria negada por engano.
        Broadcast::purge();
        require base_path('routes/channels.php');
    }

    public function test_cliente_assina_o_proprio_canal(): void
    {
        $cliente = Cliente::factory()->create();

        $this->assinar($cliente, 'cliente.'.$cliente->id)->assertOk();
    }

    public function test_cliente_nao_assina_canal_de_outro_cliente(): void
    {
        $cliente = Cliente::factory()->create();
        $outro = Cliente::factory()->create();

        $this->assinar($cliente, 'cliente.'.$outro->id)->assertForbidden();
    }

    public function test_cliente_nao_assina_canal_de_restaurante(): void
    {
        $cliente = Cliente::factory()->create();
        $restaurante = Restaurante::factory()->create();

        $this->assinar($cliente, 'restaurante.'.$restaurante->id)->assertForbidden();
    }

    public function test_restaurante_assina_o_proprio_canal(): void
    {
        $restaurante = Restaurante::factory()->create();

        $this->assinar($restaurante, 'restaurante.'.$restaurante->id)->assertOk();
    }

    public function test_restaurante_nao_assina_canal_de_outro_restaurante(): void
    {
        $restaurante = Restaurante::factory()->create();
        $outro = Restaurante::factory()->create();

        $this->assinar($restaurante, 'restaurante.'.$outro->id)->assertForbidden();
    }

    public function test_restaurante_nao_assina_canal_de_cliente(): void
    {
        $restaurante = Restaurante::factory()->create();
        $cliente = Cliente::factory()->create();

        $this->assinar($restaurante, 'cliente.'.$cliente->id)->assertForbidden();
    }

    public function test_cliente_ativo_na_fila_assina_o_canal_da_fila(): void
    {
        $entrada = ClienteFila::factory()->create();

        $this->assinar($entrada->cliente, 'fila.'.$entrada->fila_id)->assertOk();
    }

    public function test_cliente_fora_da_fila_nao_assina_o_canal_dela(): void
    {
        $entrada = ClienteFila::factory()->create();
        $intruso = Cliente::factory()->create();

        $this->assinar($intruso, 'fila.'.$entrada->fila_id)->assertForbidden();
    }

    public function test_cliente_que_ja_saiu_da_fila_nao_assina_mais_o_canal(): void
    {
        $entrada = ClienteFila::factory()->desistiu()->create();

        $this->assinar($entrada->cliente, 'fila.'.$entrada->fila_id)->assertForbidden();
    }

    public function test_sem_token_nao_assina_nada(): void
    {
        $cliente = Cliente::factory()->create();

        $this->postJson(self::ROTA, [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-cliente.'.$cliente->id,
        ])->assertUnauthorized();
    }

    private function assinar(Authenticatable $usuario, string $canal): TestResponse
    {
        $guard = $usuario instanceof Restaurante ? 'restaurante' : 'api';

        return $this->withHeader('Authorization', 'Bearer '.auth($guard)->login($usuario))
            ->postJson(self::ROTA, [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-'.$canal,
            ]);
    }
}
