<?php

namespace Tests\Feature;

use App\Models\Mesa;
use App\Models\Restaurante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/restaurante/dashboard — taxa de ocupacao instantanea (#13).
 *
 * A conta saiu do navegador e veio para ca. Mesma tecnica de token do
 * AnalyticsEndpointTest: JWT real, porque VerifyJwtTokenVersion le o header.
 */
class DashboardEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const ROTA = '/api/restaurante/dashboard';

    public function test_taxa_de_ocupacao_conta_toda_mesa_que_nao_esta_livre(): void
    {
        $restaurante = Restaurante::factory()->create();
        Mesa::factory()->count(5)->for($restaurante)->create();
        Mesa::factory()->count(2)->ocupada()->for($restaurante)->create();
        Mesa::factory()->bloqueada()->for($restaurante)->create();

        // 3 de 8 mesas fora de 'livre' => 37,5% => arredonda para 38.
        $this->comToken($restaurante)
            ->getJson(self::ROTA)
            ->assertOk()
            ->assertJsonPath('total_tables', 8)
            ->assertJsonPath('tables_available', 5)
            ->assertJsonPath('occupancy_percent', 38);
    }

    public function test_restaurante_sem_mesa_recebe_taxa_nula_e_nao_divisao_por_zero(): void
    {
        $this->comToken(Restaurante::factory()->create())
            ->getJson(self::ROTA)
            ->assertOk()
            ->assertJsonPath('total_tables', 0)
            ->assertJsonPath('occupancy_percent', null);
    }

    public function test_taxa_nao_vaza_mesa_de_outro_restaurante(): void
    {
        $meu = Restaurante::factory()->create();
        $outro = Restaurante::factory()->create();

        Mesa::factory()->count(4)->for($meu)->create();
        Mesa::factory()->count(4)->ocupada()->for($outro)->create();

        $this->comToken($meu)
            ->getJson(self::ROTA)
            ->assertOk()
            ->assertJsonPath('occupancy_percent', 0);
    }

    /** @return $this */
    private function comToken(Restaurante $restaurante): static
    {
        return $this->withHeader('Authorization', 'Bearer '.auth('restaurante')->login($restaurante));
    }
}
