<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ClienteFila;
use App\Models\Fila;
use App\Models\Restaurante;
use App\Services\EstimativaEsperaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Cobre a issue #167/#14: POST /fila, GET /fila/posicao e GET /fila/estimativa
 * passam a expor a estimativa de EstimativaEsperaService (#164/#11), até então
 * calculada mas nunca devolvida por nenhum endpoint.
 */
class FilaEstimativaEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const FUSO = 'America/Sao_Paulo';

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_post_fila_devolve_estimativa_em_fallback_sem_historico(): void
    {
        $restaurante = $this->restaurante();
        $cliente = Cliente::factory()->create();

        $resposta = $this->comToken($cliente)->postJson('/api/fila', [
            'restaurante_id' => $restaurante->id,
            'horario_reserva' => $this->futuro(),
            'qntd_pessoas' => 2,
        ]);

        $resposta->assertCreated()->assertJsonStructure([
            'message',
            'data' => ['id', 'posicao', 'espera_estimada_minutos', 'espera_estimada_segundos', 'nivel', 'amostra'],
        ]);

        // Sem histórico algum, o único nível possível é o padrão.
        $this->assertSame(EstimativaEsperaService::NIVEL_PADRAO, $resposta->json('data.nivel'));
        $this->assertSame(1, $resposta->json('data.posicao'));
        // 5 min fixos + 3 min x posição 1 = 8.
        $this->assertSame(8, $resposta->json('data.espera_estimada_minutos'));
    }

    public function test_get_fila_posicao_recalcula_estimativa_quando_a_posicao_muda(): void
    {
        $restaurante = $this->restaurante();
        $horario = $this->futuro();

        $primeiro = Cliente::factory()->create();
        $segundo = Cliente::factory()->create();

        $entradaPrimeiro = $this->comToken($primeiro)->postJson('/api/fila', [
            'restaurante_id' => $restaurante->id,
            'horario_reserva' => $horario,
            'qntd_pessoas' => 2,
        ])->json('data');

        $this->comToken($segundo)->postJson('/api/fila', [
            'restaurante_id' => $restaurante->id,
            'horario_reserva' => $horario,
            'qntd_pessoas' => 2,
        ]);

        // Antes: o segundo cliente está na posição 2 (5 + 3x2 = 11 min).
        $antes = $this->comToken($segundo)->getJson(
            '/api/fila/posicao?restaurante_id='.$restaurante->id.'&horario_reserva='.urlencode($horario)
        );
        $antes->assertOk();
        $this->assertSame(2, $antes->json('posicao'));
        $this->assertSame(11, $antes->json('espera_estimada_minutos'));
        $this->assertSame(EstimativaEsperaService::NIVEL_PADRAO, $antes->json('nivel'));

        // O primeiro sai da fila — a posição do segundo deve andar.
        $this->comToken($primeiro)->deleteJson('/api/fila/'.$entradaPrimeiro['id'])->assertOk();

        // Depois: posição 1 (5 + 3x1 = 8 min). Posição e estimativa mudam juntas.
        $depois = $this->comToken($segundo)->getJson(
            '/api/fila/posicao?restaurante_id='.$restaurante->id.'&horario_reserva='.urlencode($horario)
        );
        $depois->assertOk();
        $this->assertSame(1, $depois->json('posicao'));
        $this->assertSame(8, $depois->json('espera_estimada_minutos'));
    }

    public function test_get_fila_estimativa_usa_tamanho_atual_mais_um_antes_de_entrar(): void
    {
        $restaurante = $this->restaurante();
        $horario = $this->futuro();

        $jaNaFila = Cliente::factory()->create();
        $this->comToken($jaNaFila)->postJson('/api/fila', [
            'restaurante_id' => $restaurante->id,
            'horario_reserva' => $horario,
            'qntd_pessoas' => 2,
        ])->assertCreated();

        $curioso = Cliente::factory()->create();

        $resposta = $this->comToken($curioso)->getJson(
            '/api/fila/estimativa?restaurante_id='.$restaurante->id
                .'&horario_reserva='.urlencode($horario).'&qntd_pessoas=3'
        );

        $resposta->assertOk()->assertJsonStructure([
            'espera_estimada_minutos', 'espera_estimada_segundos', 'nivel', 'amostra', 'posicao',
        ]);

        // Já há 1 cliente ativo na fila desse horário; quem entrasse agora seria o 2º.
        $this->assertSame(2, $resposta->json('posicao'));
        $this->assertSame(EstimativaEsperaService::NIVEL_PADRAO, $resposta->json('nivel'));
    }

    public function test_get_fila_estimativa_sem_ninguem_na_fila_devolve_posicao_1(): void
    {
        $restaurante = $this->restaurante();

        $cliente = Cliente::factory()->create();

        $resposta = $this->comToken($cliente)->getJson(
            '/api/fila/estimativa?restaurante_id='.$restaurante->id
                .'&horario_reserva='.urlencode($this->futuro()).'&qntd_pessoas=2'
        );

        $resposta->assertOk();
        $this->assertSame(1, $resposta->json('posicao'));
    }

    public function test_nivel_especifico_quando_ha_historico_farto_no_mesmo_slot(): void
    {
        $restaurante = $this->restaurante();
        // Ancorado em Carbon::now(), não numa data fixa: uma data hardcoded
        // fica no passado assim que o calendário andar e o POST no fim do
        // teste (horario_reserva = $slot + 1 semana) passa a violar o
        // "after:now" da validação — foi exatamente isso que quebrou no CI.
        // +8 semanas dá folga para as 4 semanas de histórico simulado antes
        // do slot e para a semana adicional do pedido novo.
        $slot = Carbon::now(self::FUSO)->addWeeks(8)->setTime(20, 0);

        // 4 semanas x 6 posições = 24 observações no mesmo dia/horário — acima do MIN_AMOSTRA (20).
        foreach (range(0, 3) as $semana) {
            $inicio = $slot->copy()->subWeeks($semana);
            $fila = Fila::factory()->for($restaurante)->create([
                'horario_reserva' => $inicio->copy()->utc(),
                'status' => Fila::STATUS_ENCERRADA,
            ]);

            foreach (range(1, 6) as $posicao) {
                $chegada = $inicio->copy()->addMinutes($posicao - 1)->utc();
                Carbon::setTestNow($chegada->copy()->addMinutes(10 + 5 * $posicao));

                ClienteFila::factory()
                    ->for($fila)
                    ->create(['created_at' => $chegada, 'qntd_pessoas' => 2])
                    ->registrarSaida(ClienteFila::STATUS_SAIDA_ATENDIDO);

                Carbon::setTestNow();
            }
        }

        $cliente = Cliente::factory()->create();

        $resposta = $this->comToken($cliente)->postJson('/api/fila', [
            'restaurante_id' => $restaurante->id,
            'horario_reserva' => $slot->copy()->addWeek()->toIso8601String(),
            'qntd_pessoas' => 2,
        ]);

        $resposta->assertCreated();
        $this->assertSame(EstimativaEsperaService::NIVEL_ESPECIFICO, $resposta->json('data.nivel'));
        $this->assertGreaterThan(0, $resposta->json('data.amostra'));
    }

    // ───────────────────────── Helpers ─────────────────────────

    private function restaurante(): Restaurante
    {
        return Restaurante::factory()->create([
            'horario_abertura' => '00:00',
            'horario_fechamento' => '23:59',
        ]);
    }

    /** Horário futuro qualquer, aberto o dia inteiro no restaurante de teste. */
    private function futuro(): string
    {
        return Carbon::now(self::FUSO)->addDays(7)->setTime(20, 0)->toIso8601String();
    }

    /** Emite um JWT real e o anexa ao header, como o frontend faz. */
    private function comToken(Cliente $cliente): static
    {
        return $this->withHeader('Authorization', 'Bearer '.auth('api')->login($cliente));
    }
}
