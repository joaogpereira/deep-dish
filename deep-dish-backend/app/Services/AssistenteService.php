<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * O assistente do painel: pergunta em português, resposta a partir de dados reais (#178).
 *
 * ── A regra que dá sentido à issue ──
 * O modelo NUNCA produz um número por conta própria. Ele escolhe uma ferramenta,
 * a ferramenta consulta o banco, e o modelo só transforma o retorno em frase. O
 * prompt de sistema diz isso, e `RespostaAssistente::respondeuSemConsultar()`
 * deixa o chamador detectar quando ele desobedeceu.
 *
 * ── Escopo ──
 * O id do restaurante entra no construtor de FerramentasDeAnalytics e não aparece
 * em nenhum schema de ferramenta. Não há campo onde injetar outro id, nem pelo
 * texto do usuário nem por alucinação do modelo.
 *
 * ── Sem endpoint aqui ──
 * A #179 é dona da rota, da persistência e do teto de tokens. Por isso este
 * serviço recebe o histórico e devolve a conversa completa: ela acrescenta HTTP e
 * orçamento por cima, sem reabrir este contrato.
 */
class AssistenteService
{
    public function __construct(
        private readonly OpenRouterClient $llm,
        private readonly AnalyticsService $analytics,
    ) {}

    /**
     * Responde uma pergunta sobre a operação do restaurante.
     *
     * @param  list<array<string, mixed>>  $historico  conversa anterior, para a #179
     */
    public function perguntar(string $restauranteId, string $pergunta, array $historico = []): RespostaAssistente
    {
        if (trim($pergunta) === '') {
            return RespostaAssistente::falha('Faça uma pergunta sobre a operação do restaurante.');
        }

        if (! $this->llm->disponivel()) {
            return RespostaAssistente::falha('Assistente não configurado.');
        }

        $ferramentas = new FerramentasDeAnalytics($restauranteId, $this->analytics);

        $mensagens = array_merge(
            [['role' => 'system', 'content' => $this->promptDeSistema()]],
            $historico,
            [['role' => 'user', 'content' => $pergunta]],
        );

        $usadas = [];
        $uso = ['prompt' => 0, 'resposta' => 0, 'total' => 0];
        $teto = max(1, (int) config('assistente.max_iteracoes'));

        for ($volta = 1; $volta <= $teto; $volta++) {
            $resposta = $this->llm->conversar($mensagens, $ferramentas->definicoes());

            $uso = [
                'prompt' => $uso['prompt'] + $resposta->uso['prompt'],
                'resposta' => $uso['resposta'] + $resposta->uso['resposta'],
                'total' => $uso['total'] + $resposta->uso['total'],
            ];

            if (! $resposta->ok) {
                return RespostaAssistente::falha((string) $resposta->erro, $usadas, $uso);
            }

            if (! $resposta->pediuFerramenta()) {
                return RespostaAssistente::sucesso(
                    trim($resposta->texto) !== ''
                        ? trim($resposta->texto)
                        : 'Não consegui formular a resposta. Tente perguntar de outro jeito.',
                    $usadas,
                    $uso,
                    [...$mensagens, ['role' => 'assistant', 'content' => $resposta->texto]],
                );
            }

            // O client normaliza as tool_calls mas nao guarda a mensagem crua do
            // assistente, e o formato exige que ela preceda os resultados. Sem
            // reconstruir, a proxima chamada e rejeitada: ha 'tool' sem 'tool_calls'.
            $mensagens[] = $this->mensagemDoAssistente($resposta->toolCalls);

            foreach ($resposta->toolCalls as $chamada) {
                $usadas[] = $chamada['nome'];
                $mensagens[] = $this->resultado($chamada, $ferramentas);
            }
        }

        // Teto batido: a pergunta provavelmente e ambigua demais e o modelo ficou
        // pedindo ferramenta sem convergir. Melhor dizer isso do que continuar
        // gastando tokens de quem esta esperando na tela.
        Log::info('assistente.teto_de_iteracoes', [
            'restaurante' => $restauranteId,
            'ferramentas' => $usadas,
            'tokens' => $uso['total'],
        ]);

        return RespostaAssistente::falha(
            'Não consegui chegar a uma resposta. Tente perguntar de forma mais específica.',
            $usadas,
            $uso,
        );
    }

    /**
     * Executa uma ferramenta e embrulha o retorno como mensagem de papel 'tool'.
     *
     * Erro vira resultado, não exceção: o modelo lê a mensagem e costuma se
     * corrigir sozinho — pedir 90 dias depois de tentar 500, por exemplo. Abortar
     * faria uma boa pergunta morrer por um argumento que a segunda tentativa
     * acertaria.
     *
     * @param  array{id: string, nome: string, argumentos: array<string, mixed>}  $chamada
     * @return array<string, mixed>
     */
    private function resultado(array $chamada, FerramentasDeAnalytics $ferramentas): array
    {
        try {
            if (! $ferramentas->existe($chamada['nome'])) {
                throw new InvalidArgumentException(
                    "A ferramenta '{$chamada['nome']}' não existe. Use uma das ferramentas disponíveis."
                );
            }

            $conteudo = $ferramentas->executar($chamada['nome'], $chamada['argumentos']);
        } catch (InvalidArgumentException $e) {
            $conteudo = ['erro' => $e->getMessage()];
        }

        return [
            'role' => 'tool',
            'tool_call_id' => $chamada['id'],
            'name' => $chamada['nome'],
            'content' => json_encode($conteudo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * Reconstrói a mensagem do assistente a partir das tool_calls normalizadas.
     *
     * 'arguments' volta a ser string JSON porque é assim que o formato espera —
     * o client decodifica na entrada, aqui refazemos para a saída.
     *
     * O cast para object não é enfeite: `json_encode([])` em PHP produz "[]", um
     * array vazio, e o provedor recusa a requisição inteira com 400 porque o
     * formato exige objeto. Ferramenta chamada sem argumento — o caso mais comum —
     * cairia sempre nisso. Com o cast, vira "{}".
     *
     * @param  list<array{id: string, nome: string, argumentos: array<string, mixed>}>  $chamadas
     * @return array<string, mixed>
     */
    private function mensagemDoAssistente(array $chamadas): array
    {
        return [
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => array_map(fn (array $c) => [
                'id' => $c['id'],
                'type' => 'function',
                'function' => [
                    'name' => $c['nome'],
                    'arguments' => json_encode((object) $c['argumentos'], JSON_UNESCAPED_UNICODE),
                ],
            ], $chamadas),
        ];
    }

    /**
     * O prompt carrega as três regras que os critérios de aceite cobram: consultar
     * antes de afirmar, recusar o que está fora do alcance das ferramentas, e não
     * aceitar instrução de mudar de restaurante.
     */
    private function promptDeSistema(): string
    {
        return <<<'TXT'
        Você é o assistente de operação do painel do Deep Dish, usado pelo dono ou gerente
        de UM restaurante — aquele a que as ferramentas já estão amarradas.

        Regras:

        1. Nunca invente, estime ou calcule números de cabeça. Todo dado vem das ferramentas.
           Se a resposta precisa de um número, chame a ferramenta antes de responder.
        2. Se a pergunta não puder ser respondida pelas ferramentas disponíveis, diga
           claramente que não tem esse dado e sugira o que você consegue responder. Não
           improvise uma resposta plausível.
        3. As ferramentas sempre olham o restaurante de quem está logado. Se pedirem dados
           de outro restaurante, de um concorrente ou do sistema inteiro, explique que você
           só enxerga a operação deste restaurante.
        4. Responda em português do Brasil, direto, em poucas frases. Dê o número e o que
           ele significa na prática. Nada de listar tudo o que a ferramenta devolveu.
        5. Quando o período não for dito, as ferramentas usam os últimos 90 dias — diga isso
           ao apresentar o número, para a pessoa saber do que você está falando.
        TXT;
    }
}
