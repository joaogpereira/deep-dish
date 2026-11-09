<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cliente do OpenRouter (#177) — a única porta de saída do projeto para um LLM.
 *
 * OpenRouter em vez da API direta de um provedor foi decisão do backlog: sai mais
 * barato e o modelo troca por env, sem mexer em código. Por isso aqui não há nada
 * específico de fornecedor — o corpo segue o formato de chat completions, que o
 * OpenRouter aceita para todos os modelos que ele roteia.
 *
 * ── Esta classe não lança exceção ──
 * Todo erro sai como RespostaLlm::falha(). O assistente é um acessório do painel:
 * chave errada, OpenRouter fora do ar ou timeout não podem derrubar o dashboard
 * do restaurante. Quem chama decide o que mostrar, sem try/catch.
 *
 * ── Timeout curto de propósito ──
 * A requisição do usuário fica esperando esta chamada. Vale desistir rápido e
 * dizer "o assistente está indisponível" em vez de pendurar a tela — daí o
 * padrão de 20s e nenhum retry automático: retry multiplicaria a espera.
 */
class OpenRouterClient
{
    public function __construct(
        private readonly ?string $chave = null,
        private readonly ?string $modelo = null,
    ) {}

    /**
     * Dá para chamar o LLM? Falso quando falta chave ou modelo.
     *
     * Quem consulta isto antes evita uma chamada que já se sabe que falha, e pode
     * esconder a interface do assistente em vez de mostrar erro.
     */
    public function disponivel(): bool
    {
        return $this->chaveEfetiva() !== null && $this->modeloEfetivo() !== null;
    }

    /**
     * Uma ida e volta ao modelo.
     *
     * @param  list<array<string, mixed>>  $mensagens  histórico no formato de chat
     * @param  list<array<string, mixed>>  $ferramentas  definições de tool (#178)
     */
    public function conversar(array $mensagens, array $ferramentas = []): RespostaLlm
    {
        $chave = $this->chaveEfetiva();
        $modelo = $this->modeloEfetivo();

        if ($chave === null) {
            return RespostaLlm::falha('OPENROUTER_API_KEY não configurada.');
        }

        if ($modelo === null) {
            return RespostaLlm::falha('OPENROUTER_MODEL não configurado.');
        }

        $corpo = ['model' => $modelo, 'messages' => $mensagens];

        $alternativos = $this->modelosAlternativos();

        if ($alternativos !== []) {
            // O primeiro da lista e o modelo principal: 'models' e a ordem de
            // prioridade, e deixar o principal de fora o rebaixaria.
            $corpo['models'] = array_merge([$modelo], $alternativos);
        }

        if ($ferramentas !== []) {
            $corpo['tools'] = $ferramentas;
        }

        try {
            $resposta = Http::withToken($chave)
                ->withHeaders($this->cabecalhosDeIdentificacao())
                ->timeout((int) config('services.openrouter.timeout'))
                ->acceptJson()
                ->post(rtrim((string) config('services.openrouter.base_url'), '/').'/chat/completions', $corpo);
        } catch (ConnectionException $e) {
            // Timeout e DNS/conexão caem aqui. Mensagem curta no retorno, detalhe no log.
            Log::warning('openrouter.conexao', ['modelo' => $modelo, 'erro' => $e->getMessage()]);

            return RespostaLlm::falha('Assistente indisponível: tempo de resposta excedido.');
        } catch (Throwable $e) {
            Log::error('openrouter.inesperado', ['modelo' => $modelo, 'erro' => $e->getMessage()]);

            return RespostaLlm::falha('Assistente indisponível.');
        }

        if ($resposta->failed()) {
            // 401 (chave inválida), 402 (sem crédito), 429 (limite) e 5xx passam aqui.
            Log::warning('openrouter.http', [
                'modelo' => $modelo,
                'status' => $resposta->status(),
                'corpo' => mb_substr((string) $resposta->body(), 0, 500),
            ]);

            return RespostaLlm::falha($this->mensagemDeStatus($resposta->status()));
        }

        return $this->interpretar($resposta->json() ?? [], $modelo);
    }

    /**
     * Traduz o corpo do OpenRouter para RespostaLlm.
     *
     * Resposta fora do formato esperado é falha, não exceção: o modelo é remoto e
     * pode mudar de comportamento sem aviso.
     *
     * @param  array<string, mixed>  $json
     */
    private function interpretar(array $json, string $modelo): RespostaLlm
    {
        $escolha = $json['choices'][0] ?? null;

        if (! is_array($escolha)) {
            Log::warning('openrouter.formato', ['modelo' => $modelo, 'chaves' => array_keys($json)]);

            return RespostaLlm::falha('Resposta do assistente em formato inesperado.');
        }

        $uso = [
            'prompt' => (int) ($json['usage']['prompt_tokens'] ?? 0),
            'resposta' => (int) ($json['usage']['completion_tokens'] ?? 0),
            'total' => (int) ($json['usage']['total_tokens'] ?? 0),
        ];

        // Custo se acompanha desde o primeiro dia, então o uso é registrado em toda
        // chamada bem-sucedida — inclusive nas que só pediram ferramenta.
        Log::info('openrouter.uso', [
            'modelo' => $json['model'] ?? $modelo,
            'tokens_prompt' => $uso['prompt'],
            'tokens_resposta' => $uso['resposta'],
            'tokens_total' => $uso['total'],
            'id' => $json['id'] ?? null,
        ]);

        return RespostaLlm::sucesso(
            texto: (string) ($escolha['message']['content'] ?? ''),
            toolCalls: $this->extrairToolCalls($escolha['message']['tool_calls'] ?? []),
            uso: $uso,
            motivoParada: isset($escolha['finish_reason']) ? (string) $escolha['finish_reason'] : null,
        );
    }

    /**
     * Normaliza as chamadas de ferramenta para o formato que a #178 consome.
     *
     * Os argumentos vêm como STRING JSON, não como objeto. Decodificar aqui — e
     * tratar JSON inválido como ausência de argumentos — evita que cada chamador
     * repita isso e descubra do jeito ruim.
     *
     * @param  mixed  $cruas
     * @return list<array{id: string, nome: string, argumentos: array<string, mixed>}>
     */
    private function extrairToolCalls($cruas): array
    {
        if (! is_array($cruas)) {
            return [];
        }

        $chamadas = [];

        foreach ($cruas as $crua) {
            $nome = $crua['function']['name'] ?? null;

            if (! is_string($nome) || $nome === '') {
                continue;
            }

            $argumentos = json_decode((string) ($crua['function']['arguments'] ?? '{}'), true);

            $chamadas[] = [
                'id' => (string) ($crua['id'] ?? ''),
                'nome' => $nome,
                'argumentos' => is_array($argumentos) ? $argumentos : [],
            ];
        }

        return $chamadas;
    }

    /** Mensagem para o usuário por status, sem vazar detalhe de infraestrutura. */
    private function mensagemDeStatus(int $status): string
    {
        return match (true) {
            $status === 401 || $status === 403 => 'Assistente indisponível: credencial inválida.',
            $status === 402 => 'Assistente indisponível: sem crédito na conta do provedor.',
            $status === 429 => 'Assistente ocupado. Tente de novo em instantes.',
            $status >= 500 => 'Assistente indisponível: falha no provedor.',
            default => 'Assistente indisponível.',
        };
    }

    /**
     * O OpenRouter usa estes cabeçalhos para atribuir o tráfego à aplicação; eles
     * aparecem no painel de uso dele, o que ajuda a conferir custo.
     *
     * @return array<string, string>
     */
    private function cabecalhosDeIdentificacao(): array
    {
        return [
            'HTTP-Referer' => (string) config('app.url'),
            'X-Title' => (string) config('app.name'),
        ];
    }

    /**
     * Modelos de reserva, em ordem de prioridade.
     *
     * No plano gratuito o teto chega rapido (20/min, 50/dia) e um 429 derruba o
     * assistente no pior momento. O OpenRouter troca de modelo sozinho quando o
     * primeiro falha por rate limit ou indisponibilidade — basta mandar a lista.
     *
     * @return list<string>
     */
    private function modelosAlternativos(): array
    {
        $lista = config('services.openrouter.models_fallback');

        if (! is_string($lista) || trim($lista) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(trim(...), explode(',', $lista)),
            fn (string $modelo) => $modelo !== '',
        ));
    }

    private function chaveEfetiva(): ?string
    {
        $chave = $this->chave ?? config('services.openrouter.key');

        return is_string($chave) && $chave !== '' ? $chave : null;
    }

    private function modeloEfetivo(): ?string
    {
        $modelo = $this->modelo ?? config('services.openrouter.model');

        return is_string($modelo) && $modelo !== '' ? $modelo : null;
    }
}
