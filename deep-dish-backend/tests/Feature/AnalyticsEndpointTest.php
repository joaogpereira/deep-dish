<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ClienteFila;
use App\Models\Fila;
use App\Models\Mesa;
use App\Models\Restaurante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * GET /api/restaurante/analytics (#162).
 *
 * ── Por que nao usa actingAs() ──
 * A rota passa por VerifyJwtTokenVersion, que chama JWTAuth::parseToken() e le
 * o header Authorization. Com actingAs() nao existe token na requisicao, o
 * parse lanca e o middleware devolve 401 — o teste falharia por falta de token,
 * nao pela regra sendo testada. Por isso todo caso autenticado aqui emite um
 * JWT de verdade via auth('restaurante')->login(), que ja embute a claim
 * 'token_version' atraves de Restaurante::getJWTCustomClaims().
 *
 * Este e o primeiro teste do projeto que exercita a pilha de middlewares por
 * HTTP: o FilaServiceTest instancia o controller direto e nao passa por ela.
 */
class AnalyticsEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const ROTA = '/api/restaurante/analytics';

    private const FUSO = 'America/Sao_Paulo';

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ─── Acesso e autorizacao ────────────────────────────────

    public function test_restaurante_autenticado_recebe_o_payload_completo(): void
    {
        $restaurante = $this->restaurante();
        Mesa::factory()->count(2)->for($restaurante)->create();

        $fila = Fila::factory()->for($restaurante)->create();
        $this->entrada($fila, $this->local('-10 days 13:00'), 20, ClienteFila::STATUS_SAIDA_ATENDIDO);

        $resposta = $this->comToken($restaurante)->getJson(self::ROTA);

        $resposta->assertOk()->assertJsonStructure([
            'periodo' => ['data_inicio', 'data_fim', 'dias', 'fuso'],
            'procedencia' => ['fila', 'reservas', 'contem_sintetico'],
            'espera_por_dia_da_semana',
            'espera_por_faixa_horaria',
            'abandono' => ['total_entradas', 'abandonos', 'taxa', 'taxa_percentual'],
            'ocupacao',
            'giro_de_mesa' => ['mesas', 'total_atendimentos', 'duracao_media_geral_segundos'],
            'mapa_de_calor' => ['celulas', 'pico'],
            'desperdicio_de_capacidade' => [
                'total_alocacoes', 'lugares_ofertados', 'lugares_ocupados', 'lugares_ociosos',
                'desperdicio_medio_por_alocacao', 'taxa_de_ociosidade', 'taxa_de_ociosidade_percentual',
            ],
        ]);

        // As series vem completas mesmo com um unico registro no periodo.
        $this->assertCount(7, $resposta->json('espera_por_dia_da_semana'));
        $this->assertCount(24, $resposta->json('espera_por_faixa_horaria'));
        $this->assertCount(168, $resposta->json('mapa_de_calor.celulas'));
        $this->assertCount(2, $resposta->json('giro_de_mesa.mesas'));
        $this->assertSame(1, $resposta->json('abandono.total_entradas'));
    }

    public function test_sem_token_devolve_401(): void
    {
        $this->getJson(self::ROTA)->assertUnauthorized();
    }

    public function test_token_de_cliente_nao_acessa_endpoint_de_restaurante(): void
    {
        $cliente = Cliente::factory()->create();
        $token = auth('api')->login($cliente);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson(self::ROTA)
            ->assertUnauthorized();
    }

    public function test_restaurante_com_email_nao_verificado_recebe_403(): void
    {
        $restaurante = Restaurante::factory()->naoVerificado()->create();

        $this->comToken($restaurante)
            ->getJson(self::ROTA)
            ->assertForbidden()
            ->assertJsonPath('error', 'email_not_verified');
    }

    // ─── Periodo ─────────────────────────────────────────────

    public function test_periodo_padrao_cobre_os_ultimos_90_dias(): void
    {
        $restaurante = $this->restaurante();

        $resposta = $this->comToken($restaurante)->getJson(self::ROTA);

        $resposta->assertOk()
            ->assertJsonPath('periodo.dias', 90)
            ->assertJsonPath('periodo.fuso', self::FUSO)
            ->assertJsonPath('periodo.data_fim', Carbon::now(self::FUSO)->format('Y-m-d'));

        // 90 dias de janela => 90 pontos na serie diaria de ocupacao.
        $this->assertCount(90, $resposta->json('ocupacao'));
    }

    public function test_respeita_as_datas_informadas(): void
    {
        $restaurante = $this->restaurante();

        $resposta = $this->comToken($restaurante)->getJson(
            self::ROTA.'?data_inicio=2026-08-01&data_fim=2026-08-31'
        );

        $resposta->assertOk()
            ->assertJsonPath('periodo.data_inicio', '2026-08-01')
            ->assertJsonPath('periodo.data_fim', '2026-08-31')
            ->assertJsonPath('periodo.dias', 31);

        $this->assertCount(31, $resposta->json('ocupacao'));
    }

    public function test_apenas_data_fim_ainda_e_aceito(): void
    {
        // Guarda de regressao: com a regra 'after_or_equal:data_inicio' do
        // Laravel, este caso quebrava — a regra tentava ler a string literal
        // 'data_inicio' como data e rejeitava uma requisicao valida.
        $this->comToken($this->restaurante())
            ->getJson(self::ROTA.'?data_fim=2026-08-31')
            ->assertOk()
            ->assertJsonPath('periodo.data_fim', '2026-08-31')
            ->assertJsonPath('periodo.dias', 90);
    }

    public function test_data_com_fuso_na_string_e_tratada_como_dia_de_calendario(): void
    {
        // O frontend pode mandar ISO completo ('...T00:00:00Z'). Nesse caso o
        // 2o argumento do Carbon::parse e ignorado, o objeto fica em UTC e o
        // startOfDay/endOfDay recorta o dia errado — a serie voltava comecando
        // no dia anterior ao pedido.
        $resposta = $this->comToken($this->restaurante())->getJson(
            self::ROTA.'?data_inicio=2026-08-01T00:00:00Z&data_fim=2026-08-31T00:00:00Z'
        );

        $resposta->assertOk()
            ->assertJsonPath('periodo.data_inicio', '2026-08-01')
            ->assertJsonPath('periodo.data_fim', '2026-08-31')
            ->assertJsonPath('periodo.dias', 31);

        $ocupacao = $resposta->json('ocupacao');

        $this->assertCount(31, $ocupacao, 'A serie tem de cobrir os 31 dias pedidos, nem um a mais.');
        $this->assertSame('2026-08-01', $ocupacao[0]['data'], 'A serie nao pode comecar no dia anterior.');
        $this->assertSame('2026-08-31', $ocupacao[30]['data']);
    }

    public function test_data_final_antes_da_inicial_devolve_422(): void
    {
        $this->comToken($this->restaurante())
            ->getJson(self::ROTA.'?data_inicio=2026-08-31&data_fim=2026-08-01')
            ->assertStatus(422)
            ->assertJsonPath('error', 'periodo_invalido');
    }

    public function test_periodo_acima_do_teto_devolve_422_e_nao_500(): void
    {
        $this->comToken($this->restaurante())
            ->getJson(self::ROTA.'?data_inicio=2024-01-01&data_fim=2026-01-01')
            ->assertStatus(422)
            ->assertJsonPath('error', 'periodo_muito_longo');
    }

    public function test_exatamente_366_dias_e_aceito(): void
    {
        // O teto documentado e 366 dias INCLUSIVE. Este teste existe porque a
        // contagem por float ('89,999... + 1') faria o limite rejeitar o proprio
        // valor maximo — de 2026-01-01 a 2027-01-01 sao 366 dias de calendario.
        $this->comToken($this->restaurante())
            ->getJson(self::ROTA.'?data_inicio=2026-01-01&data_fim=2027-01-01')
            ->assertOk()
            ->assertJsonPath('periodo.dias', 366);
    }

    public function test_data_em_formato_invalido_devolve_422(): void
    {
        $this->comToken($this->restaurante())
            ->getJson(self::ROTA.'?data_inicio=nao-e-uma-data')
            ->assertStatus(422)
            ->assertJsonValidationErrors('data_inicio');
    }

    // ─── Isolamento ──────────────────────────────────────────

    public function test_payload_nao_traz_dado_de_outro_restaurante(): void
    {
        $meu = $this->restaurante();
        $outro = $this->restaurante();

        Mesa::factory()->for($meu)->create(['numero' => 1]);
        Mesa::factory()->count(3)->for($outro)->create();

        $minhaFila = Fila::factory()->for($meu)->create();
        $filaAlheia = Fila::factory()->for($outro)->create();

        $this->entrada($minhaFila, $this->local('-5 days 13:00'), 10, ClienteFila::STATUS_SAIDA_ATENDIDO);
        $this->entrada($filaAlheia, $this->local('-5 days 13:00'), 90, ClienteFila::STATUS_SAIDA_DESISTIU);
        $this->entrada($filaAlheia, $this->local('-4 days 13:00'), 90, ClienteFila::STATUS_SAIDA_DESISTIU);

        $resposta = $this->comToken($meu)->getJson(self::ROTA);

        $resposta->assertOk()
            ->assertJsonPath('abandono.total_entradas', 1)
            ->assertJsonPath('abandono.desistiu', 0);

        $this->assertCount(1, $resposta->json('giro_de_mesa.mesas'), 'Só as mesas do restaurante autenticado.');
    }

    // ─── Criterio de aceite: o dashboard nao pode ter mudado ──

    public function test_dashboard_continua_respondendo_como_antes(): void
    {
        $restaurante = $this->restaurante();
        Mesa::factory()->count(3)->for($restaurante)->create();

        $this->comToken($restaurante)
            ->getJson('/api/restaurante/dashboard')
            ->assertOk()
            ->assertJsonStructure(['queue_size', 'reservations_today', 'tables_available', 'total_tables'])
            ->assertJsonPath('total_tables', 3);
    }

    // ───────────────────────── Helpers ─────────────────────────

    private function restaurante(): Restaurante
    {
        return Restaurante::factory()->create([
            'horario_abertura' => '11:00',
            'horario_fechamento' => '23:00',
        ]);
    }

    /**
     * Emite um JWT real e o anexa ao header, como o frontend faz.
     *
     * @return $this
     */
    private function comToken(Restaurante $restaurante): static
    {
        return $this->withHeader('Authorization', 'Bearer '.auth('restaurante')->login($restaurante));
    }

    /** Instante relativo a agora, em horario local do restaurante. */
    private function local(string $expressao): Carbon
    {
        return Carbon::now(self::FUSO)->modify($expressao);
    }

    /**
     * Entrada de fila historica, com chegada e saida controladas.
     *
     * Mesma tecnica do AnalyticsServiceTest: tudo convertido para UTC antes de
     * gravar e antes do setTestNow(), porque o Eloquent formata o Carbon no fuso
     * que ele carrega e o setTestNow descarta o fuso ao congelar o relogio.
     */
    private function entrada(Fila $fila, Carbon $chegada, int $esperaMinutos, string $status): void
    {
        $chegadaUtc = $chegada->copy()->utc();

        Carbon::setTestNow($chegadaUtc->copy()->addMinutes($esperaMinutos));

        try {
            ClienteFila::factory()
                ->for($fila)
                ->create(['created_at' => $chegadaUtc])
                ->registrarSaida($status);
        } finally {
            Carbon::setTestNow();
        }
    }
}
