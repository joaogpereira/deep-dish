<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Restaurante;
use App\Services\EstimativaEsperaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre a issue #164: GET /restaurante/fila passa a expor a mesma estimativa
 * de EstimativaEsperaService já devolvida ao cliente em POST /fila, GET
 * /fila/posicao e GET /fila/estimativa — útil ao gerente ver a fila do
 * restaurante com a expectativa de espera de cada cliente.
 */
class FilaRestauranteEstimativaEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const ROTA = '/api/restaurante/fila';

    public function test_lista_da_fila_devolve_estimativa_para_cada_cliente(): void
    {
        $restaurante = $this->restaurante();

        $primeiro = Cliente::factory()->create();
        $segundo = Cliente::factory()->create();

        $horario = now()->addDays(3)->setTime(20, 0)->toIso8601String();

        $this->comToken($primeiro)->postJson('/api/fila', [
            'restaurante_id' => $restaurante->id,
            'horario_reserva' => $horario,
            'qntd_pessoas' => 2,
        ])->assertCreated();

        $this->comToken($segundo)->postJson('/api/fila', [
            'restaurante_id' => $restaurante->id,
            'horario_reserva' => $horario,
            'qntd_pessoas' => 4,
        ])->assertCreated();

        $resposta = $this->comTokenRestaurante($restaurante)->getJson(self::ROTA);

        $resposta->assertOk()->assertJsonCount(2);
        $resposta->assertJsonStructure([
            '*' => [
                'id', 'cliente_id', 'fila_id', 'qntd_pessoas', 'posicao',
                'espera_estimada_minutos', 'espera_estimada_segundos', 'nivel', 'amostra',
            ],
        ]);

        // Sem histórico algum, o único nível possível é o padrão (5 min fixos + 3 min x posição).
        $porPosicao = collect($resposta->json())->keyBy('posicao');

        $this->assertSame(EstimativaEsperaService::NIVEL_PADRAO, $porPosicao[1]['nivel']);
        $this->assertSame(8, $porPosicao[1]['espera_estimada_minutos']);
        $this->assertSame(480, $porPosicao[1]['espera_estimada_segundos']);
        $this->assertSame(0, $porPosicao[1]['amostra']);

        $this->assertSame(EstimativaEsperaService::NIVEL_PADRAO, $porPosicao[2]['nivel']);
        $this->assertSame(11, $porPosicao[2]['espera_estimada_minutos']);
    }

    // ───────────────────────── Helpers ─────────────────────────

    private function restaurante(): Restaurante
    {
        return Restaurante::factory()->create([
            'horario_abertura' => '00:00',
            'horario_fechamento' => '23:59',
        ]);
    }

    /** Emite um JWT real do cliente, como o frontend faz. */
    private function comToken(Cliente $cliente): static
    {
        return $this->withHeader('Authorization', 'Bearer '.auth('api')->login($cliente));
    }

    /** Emite um JWT real do restaurante, como o frontend faz. */
    private function comTokenRestaurante(Restaurante $restaurante): static
    {
        return $this->withHeader('Authorization', 'Bearer '.auth('restaurante')->login($restaurante));
    }
}
