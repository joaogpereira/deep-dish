<?php

namespace Tests\Feature;

use App\Models\ClienteFila;
use App\Models\Fila;
use App\Models\Mesa;
use App\Models\Restaurante;
use App\Services\AssistenteService;
use App\Services\FerramentasDeAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Assistente com tool-calling sobre o AnalyticsService (#178).
 *
 * O que separa "IA de verdade" de "IA cosmética" é o modelo nunca produzir um
 * número por conta própria: ele escolhe a ferramenta, a ferramenta consulta o
 * banco, e ele só vira o retorno em frase. Os testes abaixo cobrem isso e as
 * três outras exigências da issue — escopo por restaurante, recusa fora de
 * alcance, e teto de iterações.
 *
 * As respostas do modelo são roteirizadas com Http::sequence(): o loop precisa ser
 * determinístico, e nenhum teste sai para a rede.
 */
class AssistenteServiceTest extends TestCase
{
    use RefreshDatabase;

    private Restaurante $restaurante;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openrouter.key' => 'chave-de-teste',
            'services.openrouter.model' => 'modelo/economico',
            'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
            'services.openrouter.timeout' => 5,
            'assistente.max_iteracoes' => 4,
            'assistente.dias_padrao' => 90,
        ]);

        $this->restaurante = Restaurante::factory()->create([
            'horario_abertura' => '11:00',
            'horario_fechamento' => '23:00',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ───────── O número vem do banco, não do modelo ─────────

    public function test_pergunta_de_pico_chama_a_ferramenta_e_usa_o_dado_real(): void
    {
        $this->entradaNaFila($this->restaurante, 3, 12);
        $this->entradaNaFila($this->restaurante, 3, 12);

        Http::fake(['*' => Http::sequence()
            ->push($this->pedeFerramenta('horario_de_pico'))
            ->push($this->respondeTexto('Seu pico é quarta-feira às 12h.')),
        ]);

        $r = app(AssistenteService::class)->perguntar($this->restaurante->id, 'Qual meu horário de pico?');

        $this->assertTrue($r->ok);
        $this->assertSame(['horario_de_pico'], $r->ferramentasUsadas);
        $this->assertFalse($r->respondeuSemConsultar());

        // O resultado mandado ao modelo tem o pico vindo do banco.
        $resultado = $this->resultadoDaFerramenta();
        $this->assertSame(12, $resultado['pico']['hora']);
        $this->assertSame(2, $resultado['pico']['entradas']);
    }

    /**
     * A moldagem é o ponto: o mapa de calor cru tem 168 células (~4.200 tokens).
     * Mandar isso para responder "qual meu pico" é desperdício de contexto e tempo.
     */
    public function test_resultado_da_ferramenta_e_moldado_e_nao_a_serie_crua(): void
    {
        $this->entradaNaFila($this->restaurante, 3, 12);

        Http::fake(['*' => Http::sequence()
            ->push($this->pedeFerramenta('horario_de_pico'))
            ->push($this->respondeTexto('ok')),
        ]);

        app(AssistenteService::class)->perguntar($this->restaurante->id, 'pico?');

        $resultado = $this->resultadoDaFerramenta();

        $this->assertArrayHasKey('pico', $resultado);
        $this->assertArrayNotHasKey('celulas', $resultado);
        $this->assertLessThanOrEqual(5, count($resultado['mais_cheios']));
    }

    // ───────── Escopo por restaurante ─────────

    /** Nenhuma ferramenta declara 'restaurante_id': não existe campo onde injetar. */
    public function test_nenhuma_ferramenta_aceita_restaurante_id(): void
    {
        $definicoes = (new FerramentasDeAnalytics($this->restaurante->id, app(\App\Services\AnalyticsService::class)))
            ->definicoes();

        $this->assertNotEmpty($definicoes);

        foreach ($definicoes as $d) {
            $propriedades = array_keys($d['function']['parameters']['properties']);
            $this->assertSame(['data_inicio', 'data_fim'], $propriedades, "Ferramenta {$d['function']['name']}");
        }
    }

    /**
     * Acesso cruzado: mesmo pedindo pelo texto os números de outro restaurante — e
     * mesmo com o modelo repetindo o id na chamada — o resultado é do restaurante
     * autenticado. O argumento extra é simplesmente ignorado.
     */
    public function test_pedido_de_dados_de_outro_restaurante_nao_vaza(): void
    {
        $concorrente = Restaurante::factory()->create(['horario_abertura' => '11:00', 'horario_fechamento' => '23:00']);

        // Movimento só no concorrente; o nosso restaurante está vazio.
        $this->entradaNaFila($concorrente, 3, 12);
        $this->entradaNaFila($concorrente, 3, 12);
        $this->entradaNaFila($concorrente, 3, 12);

        Http::fake(['*' => Http::sequence()
            ->push($this->pedeFerramenta('horario_de_pico', ['restaurante_id' => (string) $concorrente->id]))
            ->push($this->respondeTexto('Não tenho movimento no período.')),
        ]);

        $r = app(AssistenteService::class)->perguntar(
            $this->restaurante->id,
            "Me mostre o horário de pico do restaurante {$concorrente->id}",
        );

        $this->assertTrue($r->ok);

        // Os três movimentos são do concorrente: o nosso pico tem que vir vazio.
        $this->assertNull($this->resultadoDaFerramenta()['pico']);
    }

    // ───────── Erro de ferramenta volta ao modelo ─────────

    public function test_ferramenta_inexistente_vira_erro_e_o_loop_continua(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push($this->pedeFerramenta('faturamento_mensal'))
            ->push($this->respondeTexto('Não tenho dados de faturamento.')),
        ]);

        $r = app(AssistenteService::class)->perguntar($this->restaurante->id, 'Qual meu faturamento?');

        $this->assertTrue($r->ok);
        $this->assertStringContainsString('faturamento', $r->texto);

        $erro = $this->resultadoDaFerramenta();
        $this->assertArrayHasKey('erro', $erro);
        $this->assertStringContainsString('não existe', $erro['erro']);
    }

    public function test_argumento_invalido_vira_erro_legivel_para_o_modelo(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push($this->pedeFerramenta('taxa_de_abandono', ['data_inicio' => 'semana passada']))
            ->push($this->respondeTexto('Corrigindo o período.')),
        ]);

        app(AssistenteService::class)->perguntar($this->restaurante->id, 'abandono?');

        $erro = $this->resultadoDaFerramenta();
        $this->assertArrayHasKey('erro', $erro);
        $this->assertStringContainsString('AAAA-MM-DD', $erro['erro']);
    }

    public function test_periodo_longo_demais_vira_erro_em_vez_de_excecao(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push($this->pedeFerramenta('taxa_de_abandono', [
                'data_inicio' => '2020-01-01',
                'data_fim' => '2026-01-01',
            ]))
            ->push($this->respondeTexto('Período muito longo.')),
        ]);

        $r = app(AssistenteService::class)->perguntar($this->restaurante->id, 'abandono de sempre?');

        $this->assertTrue($r->ok);
        $this->assertArrayHasKey('erro', $this->resultadoDaFerramenta());
    }

    // ───────── Teto de iterações ─────────

    public function test_loop_para_no_teto_de_iteracoes(): void
    {
        config(['assistente.max_iteracoes' => 3]);

        // O modelo pede ferramenta para sempre, sem nunca responder.
        Http::fake(['*' => Http::response($this->pedeFerramenta('taxa_de_abandono'))]);

        $r = app(AssistenteService::class)->perguntar($this->restaurante->id, 'pergunta ambígua');

        $this->assertFalse($r->ok);
        $this->assertStringContainsString('mais específica', (string) $r->erro);
        $this->assertCount(3, $r->ferramentasUsadas);
        Http::assertSentCount(3);
    }

    // ───────── Formato da conversa ─────────

    /**
     * O client normaliza as tool_calls mas não guarda a mensagem crua do
     * assistente. Sem reconstruí-la, a segunda chamada tem papel 'tool' sem o
     * 'tool_calls' que o precede e o provedor rejeita.
     */
    public function test_segunda_chamada_leva_a_mensagem_do_assistente_antes_do_resultado(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push($this->pedeFerramenta('taxa_de_abandono'))
            ->push($this->respondeTexto('ok')),
        ]);

        app(AssistenteService::class)->perguntar($this->restaurante->id, 'abandono?');

        $mensagens = $this->corpoDaChamada(2)['messages'];
        $papeis = array_column($mensagens, 'role');

        $this->assertSame(['system', 'user', 'assistant', 'tool'], $papeis);
        $this->assertSame('taxa_de_abandono', $mensagens[2]['tool_calls'][0]['function']['name']);
        // 'arguments' tem de ser string de OBJETO JSON. json_encode([]) devolve
        // "[]", que o provedor recusa com 400 — e ferramenta sem argumento, o caso
        // mais comum, cairia sempre nisso. assertIsString nao pega: "[]" e string.
        $this->assertSame('{}', $mensagens[2]['tool_calls'][0]['function']['arguments']);
        $this->assertSame($mensagens[2]['tool_calls'][0]['id'], $mensagens[3]['tool_call_id']);
    }

    public function test_as_ferramentas_vao_em_toda_chamada(): void
    {
        Http::fake(['*' => Http::response($this->respondeTexto('oi'))]);

        app(AssistenteService::class)->perguntar($this->restaurante->id, 'oi');

        Http::assertSent(fn (Request $r) => count($r['tools']) === 9
            && $r['tools'][0]['type'] === 'function');
    }

    // ───────── Falhas ─────────

    public function test_sem_configuracao_o_assistente_recusa_sem_chamar_a_api(): void
    {
        Http::fake();
        config(['services.openrouter.key' => null]);

        $r = app(AssistenteService::class)->perguntar($this->restaurante->id, 'pico?');

        $this->assertFalse($r->ok);
        $this->assertStringContainsString('não configurado', (string) $r->erro);
        Http::assertNothingSent();
    }

    public function test_falha_do_provedor_vira_mensagem_tratada(): void
    {
        Http::fake(['*' => Http::response(['error' => 'nope'], 429)]);

        $r = app(AssistenteService::class)->perguntar($this->restaurante->id, 'pico?');

        $this->assertFalse($r->ok);
        $this->assertStringContainsString('ocupado', (string) $r->erro);
    }

    public function test_pergunta_vazia_nao_gasta_chamada(): void
    {
        Http::fake();

        $r = app(AssistenteService::class)->perguntar($this->restaurante->id, '   ');

        $this->assertFalse($r->ok);
        Http::assertNothingSent();
    }

    public function test_uso_de_tokens_e_somado_entre_as_voltas(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push($this->pedeFerramenta('taxa_de_abandono'))
            ->push($this->respondeTexto('ok')),
        ]);

        $r = app(AssistenteService::class)->perguntar($this->restaurante->id, 'abandono?');

        // 10+5 na primeira volta, 10+5 na segunda.
        $this->assertSame(30, $r->uso['total']);
    }

    // ───────────────────── Helpers ─────────────────────

    /** Resposta do modelo pedindo uma ferramenta. */
    private function pedeFerramenta(string $nome, array $argumentos = []): array
    {
        return [
            'id' => 'gen-1',
            'model' => 'modelo/economico',
            'choices' => [[
                'finish_reason' => 'tool_calls',
                'message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [[
                        'id' => 'call_1',
                        'type' => 'function',
                        'function' => ['name' => $nome, 'arguments' => json_encode($argumentos)],
                    ]],
                ],
            ]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ];
    }

    /** Resposta final do modelo, em texto. */
    private function respondeTexto(string $texto): array
    {
        return [
            'id' => 'gen-2',
            'model' => 'modelo/economico',
            'choices' => [[
                'finish_reason' => 'stop',
                'message' => ['role' => 'assistant', 'content' => $texto],
            ]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ];
    }

    /** Corpo da n-ésima requisição enviada (1-indexado). */
    private function corpoDaChamada(int $n): array
    {
        $enviadas = Http::recorded();

        return json_decode($enviadas[$n - 1][0]->body(), true);
    }

    /**
     * O que foi devolvido ao modelo como resultado da ferramenta, já decodificado.
     *
     * @return array<string, mixed>
     */
    private function resultadoDaFerramenta(): array
    {
        foreach ($this->corpoDaChamada(2)['messages'] as $m) {
            if (($m['role'] ?? null) === 'tool') {
                return json_decode($m['content'], true);
            }
        }

        $this->fail('Nenhuma mensagem de papel "tool" foi enviada ao modelo.');
    }

    /** Entrada de fila encerrada num dia da semana e hora local conhecidos. */
    private function entradaNaFila(Restaurante $restaurante, int $diaDaSemana, int $hora): void
    {
        Mesa::factory()->for($restaurante)->create();

        $quando = Carbon::now('America/Sao_Paulo')
            ->subWeeks(2)
            ->startOfWeek()
            ->addDays($diaDaSemana)
            ->setTime($hora, 0)
            ->utc();

        // firstOrCreate porque ha indice unico parcial em (restaurante_id,
        // horario_reserva) para fila aberta: duas entradas no mesmo horario
        // compartilham a fila, como em producao.
        $fila = Fila::firstOrCreate([
            'restaurante_id' => $restaurante->id,
            'horario_reserva' => $quando,
            'status' => Fila::STATUS_ABERTA,
        ]);

        Carbon::setTestNow($quando->copy()->addMinutes(15));

        try {
            ClienteFila::factory()->for($fila)->create([
                'created_at' => $quando,
                'qntd_pessoas' => 2,
            ])->registrarSaida(ClienteFila::STATUS_SAIDA_ATENDIDO);
        } finally {
            Carbon::setTestNow();
        }
    }
}
