<?php

namespace Tests\Feature;

use App\Services\OpenRouterClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Cliente do OpenRouter (#177).
 *
 * O ponto da issue não é "a chamada funciona" — é que **nada aqui derruba o
 * painel**. O assistente é acessório: chave ausente, credencial inválida, limite
 * do provedor, timeout e resposta malformada precisam virar erro tratado, nunca
 * exceção solta. Por isso quase todo teste aqui é de falha.
 *
 * Http::fake() em todos: nenhum teste sai para a rede.
 */
class OpenRouterClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openrouter.key' => 'chave-de-teste',
            'services.openrouter.model' => 'modelo/economico',
            'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
            'services.openrouter.timeout' => 5,
        ]);
    }

    // ───────────── Ida e volta ─────────────

    public function test_ida_e_volta_devolve_o_texto_do_modelo(): void
    {
        Http::fake(['*' => Http::response($this->corpoDeResposta('Sexta às 20h é seu pico.'))]);

        $resposta = app(OpenRouterClient::class)->conversar([
            ['role' => 'user', 'content' => 'Qual meu horário de pico?'],
        ]);

        $this->assertTrue($resposta->ok);
        $this->assertSame('Sexta às 20h é seu pico.', $resposta->texto);
        $this->assertNull($resposta->erro);
    }

    public function test_manda_o_modelo_configurado_por_env(): void
    {
        Http::fake(['*' => Http::response($this->corpoDeResposta('ok'))]);
        config(['services.openrouter.model' => 'outro/modelo-barato']);

        app(OpenRouterClient::class)->conversar([['role' => 'user', 'content' => 'oi']]);

        Http::assertSent(fn (Request $r) => $r['model'] === 'outro/modelo-barato'
            && $r->hasHeader('Authorization', 'Bearer chave-de-teste'));
    }

    // ───────────── Consumo de tokens ─────────────

    public function test_consumo_de_tokens_fica_registrado(): void
    {
        Http::fake(['*' => Http::response($this->corpoDeResposta('ok', prompt: 120, resposta: 45))]);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn (string $mensagem, array $contexto) => $mensagem === 'openrouter.uso'
                && $contexto['tokens_prompt'] === 120
                && $contexto['tokens_resposta'] === 45
                && $contexto['tokens_total'] === 165);

        $resposta = app(OpenRouterClient::class)->conversar([['role' => 'user', 'content' => 'oi']]);

        $this->assertSame(165, $resposta->uso['total']);
    }

    // ───────────── Falhas, que é o que a issue cobra ─────────────

    public function test_chave_ausente_nao_chama_a_api_e_devolve_erro_tratado(): void
    {
        Http::fake();
        config(['services.openrouter.key' => null]);

        $resposta = app(OpenRouterClient::class)->conversar([['role' => 'user', 'content' => 'oi']]);

        $this->assertFalse($resposta->ok);
        $this->assertStringContainsString('OPENROUTER_API_KEY', (string) $resposta->erro);
        Http::assertNothingSent();
    }

    public function test_modelo_ausente_devolve_erro_em_vez_de_chutar_um_slug(): void
    {
        Http::fake();
        config(['services.openrouter.model' => null]);

        $resposta = app(OpenRouterClient::class)->conversar([['role' => 'user', 'content' => 'oi']]);

        $this->assertFalse($resposta->ok);
        $this->assertStringContainsString('OPENROUTER_MODEL', (string) $resposta->erro);
        Http::assertNothingSent();
    }

    /** @return array<string, array{0: int, 1: string}> */
    public static function statusDeFalha(): array
    {
        return [
            'credencial invalida' => [401, 'credencial'],
            'sem credito' => [402, 'crédito'],
            'limite do provedor' => [429, 'ocupado'],
            'erro do provedor' => [503, 'provedor'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('statusDeFalha')]
    public function test_status_de_erro_vira_falha_tratada(int $status, string $trecho): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'nope']], $status)]);

        $resposta = app(OpenRouterClient::class)->conversar([['role' => 'user', 'content' => 'oi']]);

        $this->assertFalse($resposta->ok);
        $this->assertStringContainsString($trecho, (string) $resposta->erro);
    }

    public function test_timeout_nao_trava_a_requisicao_do_usuario(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $resposta = app(OpenRouterClient::class)->conversar([['role' => 'user', 'content' => 'oi']]);

        $this->assertFalse($resposta->ok);
        $this->assertStringContainsString('tempo de resposta', (string) $resposta->erro);
    }

    public function test_resposta_malformada_vira_falha_e_nao_excecao(): void
    {
        Http::fake(['*' => Http::response(['sem' => 'choices'])]);

        $resposta = app(OpenRouterClient::class)->conversar([['role' => 'user', 'content' => 'oi']]);

        $this->assertFalse($resposta->ok);
        $this->assertStringContainsString('formato inesperado', (string) $resposta->erro);
    }

    // ───────────── Preparo para a #178 ─────────────

    public function test_disponivel_reflete_chave_e_modelo(): void
    {
        $this->assertTrue(app(OpenRouterClient::class)->disponivel());

        config(['services.openrouter.key' => '']);
        $this->assertFalse(app(OpenRouterClient::class)->disponivel());
    }

    /**
     * Os argumentos da ferramenta vêm como STRING JSON, não objeto. Decodificar no
     * client evita que a #178 e todo chamador futuro descubram isso do jeito ruim.
     */
    public function test_tool_call_chega_decodificada(): void
    {
        Http::fake(['*' => Http::response([
            'id' => 'gen-1',
            'model' => 'modelo/economico',
            'choices' => [[
                'finish_reason' => 'tool_calls',
                'message' => [
                    'content' => null,
                    'tool_calls' => [[
                        'id' => 'call_1',
                        'function' => [
                            'name' => 'taxa_de_abandono',
                            'arguments' => '{"dias":30}',
                        ],
                    ]],
                ],
            ]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ])]);

        $resposta = app(OpenRouterClient::class)->conversar(
            [['role' => 'user', 'content' => 'qual a taxa de abandono?']],
            [['type' => 'function', 'function' => ['name' => 'taxa_de_abandono']]],
        );

        $this->assertTrue($resposta->pediuFerramenta());
        $this->assertSame('taxa_de_abandono', $resposta->toolCalls[0]['nome']);
        $this->assertSame(['dias' => 30], $resposta->toolCalls[0]['argumentos']);
        $this->assertSame('tool_calls', $resposta->motivoParada);
    }

    /** Argumento com JSON quebrado não pode explodir: vira ausência de argumentos. */
    public function test_argumento_invalido_nao_derruba_a_leitura(): void
    {
        Http::fake(['*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => null,
                    'tool_calls' => [[
                        'id' => 'call_1',
                        'function' => ['name' => 'taxa_de_abandono', 'arguments' => '{quebrado'],
                    ]],
                ],
            ]],
        ])]);

        $resposta = app(OpenRouterClient::class)->conversar([['role' => 'user', 'content' => 'oi']]);

        $this->assertTrue($resposta->ok);
        $this->assertSame([], $resposta->toolCalls[0]['argumentos']);
    }

    /**
     * No plano gratuito sao 20 req/min e 50 req/dia: um 429 derruba o assistente no
     * pior momento. O OpenRouter troca de modelo sozinho se receber a lista.
     */
    public function test_lista_de_fallback_vai_no_corpo_com_o_principal_na_frente(): void
    {
        Http::fake(['*' => Http::response($this->corpoDeResposta('ok'))]);
        config(['services.openrouter.models_fallback' => 'reserva/um:free , reserva/dois:free']);

        app(OpenRouterClient::class)->conversar([['role' => 'user', 'content' => 'oi']]);

        Http::assertSent(fn (Request $r) => $r['models'] === [
            'modelo/economico',
            'reserva/um:free',
            'reserva/dois:free',
        ]);
    }

    public function test_sem_fallback_configurado_o_corpo_nao_leva_models(): void
    {
        Http::fake(['*' => Http::response($this->corpoDeResposta('ok'))]);
        config(['services.openrouter.models_fallback' => null]);

        app(OpenRouterClient::class)->conversar([['role' => 'user', 'content' => 'oi']]);

        Http::assertSent(fn (Request $r) => ! isset($r['models']) && $r['model'] === 'modelo/economico');
    }

    public function test_ferramentas_so_vao_no_corpo_quando_existem(): void
    {
        Http::fake(['*' => Http::response($this->corpoDeResposta('ok'))]);

        app(OpenRouterClient::class)->conversar([['role' => 'user', 'content' => 'oi']]);

        Http::assertSent(fn (Request $r) => ! isset($r['tools']));
    }

    /** @return array<string, mixed> */
    private function corpoDeResposta(string $texto, int $prompt = 10, int $resposta = 5): array
    {
        return [
            'id' => 'gen-teste',
            'model' => 'modelo/economico',
            'choices' => [[
                'finish_reason' => 'stop',
                'message' => ['role' => 'assistant', 'content' => $texto],
            ]],
            'usage' => [
                'prompt_tokens' => $prompt,
                'completion_tokens' => $resposta,
                'total_tokens' => $prompt + $resposta,
            ],
        ];
    }
}
