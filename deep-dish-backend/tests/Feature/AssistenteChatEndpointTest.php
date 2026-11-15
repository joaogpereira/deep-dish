<?php

namespace Tests\Feature;

use App\Models\AssistenteConversa;
use App\Models\AssistenteMensagem;
use App\Models\AssistenteUso;
use App\Models\Restaurante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * POST /api/restaurante/assistente/chat (#179).
 *
 * Um bloco por critério de aceite: contexto entre mensagens, teto de tokens com
 * mensagem clara, clique repetido sem custo multiplicado, e conversa isolada por
 * restaurante. O modelo é roteirizado com Http::fake — nenhum teste sai para a
 * rede. Autenticação por JWT real, pelo mesmo motivo do AnalyticsEndpointTest.
 */
class AssistenteChatEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const ROTA = '/api/restaurante/assistente/chat';

    private Restaurante $restaurante;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openrouter.key' => 'chave-de-teste',
            'services.openrouter.model' => 'modelo/economico',
            'services.openrouter.models_fallback' => null,
            'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
            'services.openrouter.timeout' => 5,
            'assistente.max_iteracoes' => 4,
            'assistente.historico_turnos' => 6,
            'assistente.teto_tokens_dia' => 1000,
            'assistente.requisicoes_por_minuto' => 10,
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

    // ─── Conversa e contexto ─────────────────────────────────

    public function test_primeira_pergunta_cria_conversa_e_grava_o_turno(): void
    {
        Http::fake(['*' => Http::response($this->respondeTexto('Olá! Posso ajudar com a operação.'))]);

        $resposta = $this->perguntar('Oi');

        $resposta->assertOk()
            ->assertJsonPath('resposta', 'Olá! Posso ajudar com a operação.')
            ->assertJsonPath('ferramentas_usadas', []);

        $conversa = AssistenteConversa::findOrFail($resposta->json('conversa_id'));
        $this->assertSame($this->restaurante->id, $conversa->restaurante_id);
        $this->assertSame(['user', 'assistant'], $conversa->mensagens->pluck('role')->all());
        $this->assertSame(15, (int) AssistenteUso::sum('tokens_total'));
    }

    public function test_segunda_pergunta_leva_o_turno_anterior_como_contexto(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push($this->respondeTexto('Seu pico é quarta às 12h.'))
            ->push($this->respondeTexto('Na quinta o movimento cai.')),
        ]);

        $conversaId = $this->perguntar('Qual meu pico?')->json('conversa_id');
        $this->perguntar('E no dia seguinte?', $conversaId)->assertOk()->assertJsonPath('conversa_id', $conversaId);

        $this->assertSame([
            ['role' => 'user', 'content' => 'Qual meu pico?'],
            ['role' => 'assistant', 'content' => 'Seu pico é quarta às 12h.'],
            ['role' => 'user', 'content' => 'E no dia seguinte?'],
        ], $this->mensagensSemSistema(2));

        $this->assertSame(4, AssistenteMensagem::count());
    }

    public function test_troca_de_ferramenta_e_gravada_mas_nao_volta_como_contexto(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push($this->pedeFerramenta('horario_de_pico'))
            ->push($this->respondeTexto('Seu pico é quarta às 12h.'))
            ->push($this->respondeTexto('De nada!')),
        ]);

        $primeira = $this->perguntar('Qual meu pico?');
        $primeira->assertOk()->assertJsonPath('ferramentas_usadas', ['horario_de_pico']);

        $roles = AssistenteMensagem::orderBy('posicao')->pluck('role')->all();
        $this->assertSame(['user', 'assistant', 'tool', 'assistant'], $roles);

        $this->perguntar('Obrigado', $primeira->json('conversa_id'))->assertOk();

        $this->assertSame([
            ['role' => 'user', 'content' => 'Qual meu pico?'],
            ['role' => 'assistant', 'content' => 'Seu pico é quarta às 12h.'],
            ['role' => 'user', 'content' => 'Obrigado'],
        ], $this->mensagensSemSistema(3));
    }

    public function test_historico_enviado_respeita_o_limite_de_turnos(): void
    {
        config(['assistente.historico_turnos' => 1]);

        Http::fake(['*' => Http::sequence()
            ->push($this->respondeTexto('R1'))
            ->push($this->respondeTexto('R2'))
            ->push($this->respondeTexto('R3')),
        ]);

        $conversaId = $this->perguntar('P1')->json('conversa_id');
        $this->perguntar('P2', $conversaId)->assertOk();
        $this->perguntar('P3', $conversaId)->assertOk();

        $this->assertSame([
            ['role' => 'user', 'content' => 'P2'],
            ['role' => 'assistant', 'content' => 'R2'],
            ['role' => 'user', 'content' => 'P3'],
        ], $this->mensagensSemSistema(3));
    }

    // ─── Isolamento entre restaurantes ───────────────────────

    public function test_restaurante_nao_acessa_conversa_de_outro(): void
    {
        $outro = Restaurante::factory()->create();
        $conversaDoOutro = AssistenteConversa::create(['restaurante_id' => $outro->id]);
        Http::fake();

        $this->perguntar('Oi', $conversaDoOutro->id)
            ->assertNotFound()
            ->assertJsonPath('error', 'conversa_nao_encontrada');

        Http::assertNothingSent();
    }

    public function test_requisicao_sem_token_e_recusada(): void
    {
        $this->postJson(self::ROTA, ['mensagem' => 'Oi'])->assertUnauthorized();
    }

    // ─── Orçamento e rate limit ──────────────────────────────

    public function test_teto_de_tokens_atingido_devolve_mensagem_clara_sem_chamar_o_modelo(): void
    {
        $this->usoRegistrado(1000);
        Http::fake();

        $this->perguntar('Oi')
            ->assertStatus(429)
            ->assertJsonPath('error', 'assistente_teto_tokens')
            ->assertJsonPath('message', 'O limite diário do assistente foi atingido. Ele volta a funcionar amanhã.');

        Http::assertNothingSent();
    }

    public function test_consumo_de_ontem_nao_conta_no_teto_de_hoje(): void
    {
        // 10h em São Paulo; 02:59 UTC de hoje ainda é ontem no fuso do restaurante.
        Carbon::setTestNow(Carbon::parse('2026-11-14 13:00:00', 'UTC'));
        $this->usoRegistrado(1000, Carbon::parse('2026-11-14 02:59:00', 'UTC'));

        Http::fake(['*' => Http::response($this->respondeTexto('Olá!'))]);

        $this->perguntar('Oi')->assertOk();
    }

    public function test_consumo_de_outro_restaurante_nao_conta_no_teto(): void
    {
        $outro = Restaurante::factory()->create();
        AssistenteUso::create([
            'restaurante_id' => $outro->id,
            'tokens_prompt' => 1000, 'tokens_resposta' => 0, 'tokens_total' => 1000, 'ok' => true,
        ]);

        Http::fake(['*' => Http::response($this->respondeTexto('Olá!'))]);

        $this->perguntar('Oi')->assertOk();
    }

    public function test_clique_repetido_com_pergunta_em_andamento_nao_chama_o_modelo(): void
    {
        Cache::lock("assistente:chat:{$this->restaurante->id}", 60)->get();
        Http::fake();

        $this->perguntar('Oi')
            ->assertStatus(409)
            ->assertJsonPath('error', 'assistente_ocupado');

        Http::assertNothingSent();
    }

    public function test_trava_e_liberada_ao_fim_da_resposta(): void
    {
        Http::fake(['*' => Http::response($this->respondeTexto('Olá!'))]);

        $this->perguntar('Oi')->assertOk();
        $this->perguntar('Oi de novo')->assertOk();
    }

    public function test_rate_limit_por_restaurante_devolve_mensagem_clara(): void
    {
        config(['assistente.requisicoes_por_minuto' => 2]);
        Http::fake(['*' => Http::response($this->respondeTexto('Olá!'))]);

        $this->perguntar('1')->assertOk();
        $this->perguntar('2')->assertOk();
        $this->perguntar('3')
            ->assertStatus(429)
            ->assertJsonPath('error', 'assistente_rate_limit');

        Http::assertSentCount(2);
    }

    // ─── Falhas ──────────────────────────────────────────────

    public function test_falha_do_provedor_devolve_503_e_nao_grava_o_turno(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'fora']], 500)]);

        $this->perguntar('Oi')
            ->assertStatus(503)
            ->assertJsonPath('error', 'assistente_indisponivel');

        $this->assertSame(0, AssistenteConversa::count());
        $this->assertSame(0, AssistenteMensagem::count());

        $uso = AssistenteUso::sole();
        $this->assertFalse($uso->ok);
        $this->assertNull($uso->conversa_id);
    }

    public function test_falha_depois_de_gastar_tokens_conta_no_teto(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push($this->pedeFerramenta('horario_de_pico'))
            ->push(['error' => ['message' => 'fora']], 500),
        ]);

        $this->perguntar('Qual meu pico?')->assertStatus(503);

        $this->assertSame(15, (int) AssistenteUso::sum('tokens_total'));
    }

    public function test_mensagem_vazia_e_recusada(): void
    {
        Http::fake();

        $this->perguntar('   ')->assertUnprocessable()->assertJsonValidationErrors('mensagem');

        Http::assertNothingSent();
    }

    // ─── Auxiliares ──────────────────────────────────────────

    private function perguntar(string $mensagem, ?string $conversaId = null): \Illuminate\Testing\TestResponse
    {
        return $this
            ->withHeader('Authorization', 'Bearer '.auth('restaurante')->login($this->restaurante))
            ->postJson(self::ROTA, array_filter(['mensagem' => $mensagem, 'conversa_id' => $conversaId]));
    }

    private function usoRegistrado(int $tokens, ?Carbon $quando = null): void
    {
        $uso = AssistenteUso::create([
            'restaurante_id' => $this->restaurante->id,
            'tokens_prompt' => $tokens, 'tokens_resposta' => 0, 'tokens_total' => $tokens, 'ok' => true,
        ]);

        if ($quando) {
            $uso->forceFill(['created_at' => $quando])->save();
        }
    }

    /**
     * Mensagens enviadas ao modelo na n-ésima chamada, sem o prompt de sistema.
     *
     * @return list<array<string, mixed>>
     */
    private function mensagensSemSistema(int $n): array
    {
        $corpo = json_decode(Http::recorded()[$n - 1][0]->body(), true);

        return array_values(array_filter($corpo['messages'], fn (array $m) => $m['role'] !== 'system'));
    }

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
}
