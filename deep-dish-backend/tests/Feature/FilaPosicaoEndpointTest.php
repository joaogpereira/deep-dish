<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ClienteFila;
use App\Models\Fila;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/fila/posicao — o 404 diz por que o cliente saiu (#173).
 *
 * E o que deixa a tela da fila separar "foi promovido para mesa" de "foi
 * removido" sem listar reservas para adivinhar.
 */
class FilaPosicaoEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_quem_esta_na_fila_recebe_a_posicao(): void
    {
        $entrada = ClienteFila::factory()->create();

        $this->consultar($entrada->cliente, $entrada->fila)
            ->assertOk()
            ->assertJsonPath('posicao', 1);
    }

    public function test_promovido_recebe_404_com_status_atendido_mesmo_com_a_fila_encerrada(): void
    {
        $fila = Fila::factory()->encerrada()->create();
        $entrada = ClienteFila::factory()->for($fila)->atendido()->create();

        $this->consultar($entrada->cliente, $fila)
            ->assertNotFound()
            ->assertJsonPath('status_saida', ClienteFila::STATUS_SAIDA_ATENDIDO);
    }

    public function test_removido_pelo_restaurante_recebe_404_com_status_removido(): void
    {
        $entrada = ClienteFila::factory()->removido()->create();

        $this->consultar($entrada->cliente, $entrada->fila)
            ->assertNotFound()
            ->assertJsonPath('status_saida', ClienteFila::STATUS_SAIDA_REMOVIDO);
    }

    public function test_quem_nunca_entrou_recebe_status_nulo_e_nao_a_saida_de_outro(): void
    {
        $fila = Fila::factory()->create();
        ClienteFila::factory()->for($fila)->atendido()->create();

        $this->consultar(Cliente::factory()->create(), $fila)
            ->assertNotFound()
            ->assertJsonPath('status_saida', null);
    }

    private function consultar(Cliente $cliente, Fila $fila): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.auth('api')->login($cliente))
            ->getJson('/api/fila/posicao?'.http_build_query([
                'restaurante_id' => $fila->restaurante_id,
                'horario_reserva' => $fila->horario_reserva->toIso8601String(),
            ]));
    }
}
