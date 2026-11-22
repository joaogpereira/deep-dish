<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * As métricas do AnalyticsService expostas como ferramentas do assistente (#178).
 *
 * ── Por que o restaurante não é parâmetro ──
 * O id vem do construtor, não do schema das ferramentas. Nenhuma delas declara
 * 'restaurante_id', então não existe campo onde o modelo — ou alguém escrevendo
 * "me mostre os números do restaurante X" — possa injetar outro. Não é validação
 * contra acesso cruzado: é a ausência do caminho. O pior que acontece se o modelo
 * inventar o argumento é ele ser ignorado.
 *
 * ── Por que o resultado é moldado, e não o retorno cru ──
 * Três métricas devolvem séries grandes: o mapa de calor tem 168 células (~4.200
 * tokens), a ocupação tem uma por dia (~4.100 em 90 dias) e a espera por hora tem
 * 24. Mandar isso cru para o modelo gasta contexto e tempo para responder "qual
 * meu horário de pico?", cuja resposta são duas linhas.
 *
 * Então cada ferramenta devolve o recorte que responde à pergunta do docblock —
 * pico, extremos, piores casos — e os números continuam vindo do banco. O modelo
 * nunca inventa valor; ele só não recebe a planilha inteira para achar o maior.
 *
 * Segundos viram minutos arredondados: além de legível, encurta o número.
 */
class FerramentasDeAnalytics
{
    public function __construct(
        private readonly string $restauranteId,
        private readonly AnalyticsService $analytics,
    ) {}

    /**
     * Definições no formato que o OpenRouter espera, para irem em `tools`.
     *
     * As descrições saem dos docblocks do AnalyticsService, que por convenção
     * começam dizendo qual pergunta o método responde — é esse texto que faz o
     * modelo escolher a ferramenta certa.
     *
     * @return list<array<string, mixed>>
     */
    public function definicoes(): array
    {
        $catalogo = [
            'horario_de_pico' => 'Em que combinação de dia da semana e horário a casa enche. Use para perguntas sobre horário de pico, movimento e quando há mais gente.',
            'espera_por_dia_da_semana' => 'Em que dia da semana o cliente espera mais na fila.',
            'pior_horario_de_espera' => 'Qual horário do dia tem a pior espera na fila.',
            'taxa_de_abandono' => 'Que fração de quem entrou na fila foi embora sem ser atendido.',
            'ocupacao' => 'Quanto da capacidade instalada rendeu no período: média, melhor e pior dia.',
            'giro_de_mesa' => 'Quais mesas mais giram e quanto tempo o cliente fica sentado.',
            'desperdicio_de_capacidade' => 'Quanto lugar sobra nas mesas que a fila alocou — mede a qualidade do encaixe entre grupo e mesa.',
            'tempos_de_limpeza' => 'Quanto tempo a mesa fica fora de operação entre um cliente e o próximo, separando espera parada de limpeza em si.',
            'procedencia_dos_dados' => 'Se os números do período são de uso real ou de dado gerado para demonstração.',
        ];

        $definicoes = [];

        foreach ($catalogo as $nome => $descricao) {
            $definicoes[] = [
                'type' => 'function',
                'function' => [
                    'name' => $nome,
                    'description' => $descricao,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'data_inicio' => [
                                'type' => 'string',
                                'description' => 'Primeiro dia do período, no formato AAAA-MM-DD. Omita para usar os últimos '.$this->diasPadrao().' dias.',
                            ],
                            'data_fim' => [
                                'type' => 'string',
                                'description' => 'Último dia do período, no formato AAAA-MM-DD. Omita para usar até hoje.',
                            ],
                        ],
                        'required' => [],
                        'additionalProperties' => false,
                    ],
                ],
            ];
        }

        return $definicoes;
    }

    public function existe(string $nome): bool
    {
        return in_array($nome, array_column(
            array_column($this->definicoes(), 'function'), 'name'
        ), true);
    }

    /**
     * Executa a ferramenta e devolve o resultado moldado.
     *
     * Lança InvalidArgumentException para período inválido — quem chama devolve a
     * mensagem ao modelo como resultado da ferramenta, para ele corrigir e tentar
     * de novo em vez de a pergunta morrer por um argumento ruim.
     *
     * @param  array<string, mixed>  $argumentos
     * @return array<string, mixed>
     */
    public function executar(string $nome, array $argumentos): array
    {
        [$inicio, $fim] = $this->periodo($argumentos);

        return match ($nome) {
            'horario_de_pico' => $this->horarioDePico($inicio, $fim),
            'espera_por_dia_da_semana' => $this->esperaPorDiaDaSemana($inicio, $fim),
            'pior_horario_de_espera' => $this->piorHorarioDeEspera($inicio, $fim),
            'taxa_de_abandono' => $this->abandono($inicio, $fim),
            'ocupacao' => $this->ocupacao($inicio, $fim),
            'giro_de_mesa' => $this->giroDeMesa($inicio, $fim),
            'desperdicio_de_capacidade' => $this->desperdicio($inicio, $fim),
            'tempos_de_limpeza' => $this->limpeza($inicio, $fim),
            'procedencia_dos_dados' => $this->analytics->procedenciaDoHistorico($this->restauranteId, $inicio, $fim),
            default => throw new InvalidArgumentException("Ferramenta '{$nome}' não existe."),
        };
    }

    // ───────────────────── As moldagens ─────────────────────

    /**
     * Do mapa de calor, só o pico e os cinco horários mais cheios. A grade de 168
     * células existe para desenhar o gráfico na tela, não para o modelo varrer.
     *
     * @return array<string, mixed>
     */
    private function horarioDePico(CarbonImmutable $inicio, CarbonImmutable $fim): array
    {
        $mapa = $this->analytics->mapaDeCalorDeDemanda($this->restauranteId, $inicio, $fim);

        $ocupadas = array_values(array_filter($mapa['celulas'], fn (array $c) => $c['entradas'] > 0));
        usort($ocupadas, fn (array $a, array $b) => $b['entradas'] <=> $a['entradas']);

        return [
            'pico' => $mapa['pico'] === null ? null : [
                'dia' => $mapa['pico']['nome'],
                'hora' => $mapa['pico']['hora'],
                'entradas' => $mapa['pico']['entradas'],
                'pessoas' => $mapa['pico']['pessoas'],
            ],
            'mais_cheios' => array_map(fn (array $c) => [
                'dia' => $c['nome'],
                'hora' => $c['hora'],
                'entradas' => $c['entradas'],
            ], array_slice($ocupadas, 0, 5)),
            'periodo' => $this->rotuloDoPeriodo($inicio, $fim),
        ];
    }

    /** @return array<string, mixed> */
    private function esperaPorDiaDaSemana(CarbonImmutable $inicio, CarbonImmutable $fim): array
    {
        $dias = $this->analytics->tempoMedioDeEsperaPorDiaDaSemana($this->restauranteId, $inicio, $fim);

        return [
            'dias' => array_map(fn (array $d) => [
                'dia' => $d['nome'],
                'entradas' => $d['entradas'],
                'espera_media_min' => $this->emMinutos($d['tempo_medio_segundos']),
            ], $dias),
            'periodo' => $this->rotuloDoPeriodo($inicio, $fim),
        ];
    }

    /**
     * Das 24 faixas, as cinco piores entre as que tiveram movimento. Hora vazia
     * tem espera zero e apareceria como "a melhor", o que seria enganoso.
     *
     * @return array<string, mixed>
     */
    private function piorHorarioDeEspera(CarbonImmutable $inicio, CarbonImmutable $fim): array
    {
        $faixas = $this->analytics->tempoMedioDeEsperaPorFaixaHoraria($this->restauranteId, $inicio, $fim);

        $comMovimento = array_values(array_filter($faixas, fn (array $f) => $f['entradas'] > 0));
        usort($comMovimento, fn (array $a, array $b) => $b['tempo_medio_segundos'] <=> $a['tempo_medio_segundos']);

        return [
            'piores_horarios' => array_map(fn (array $f) => [
                'hora' => $f['hora'],
                'entradas' => $f['entradas'],
                'espera_media_min' => $this->emMinutos($f['tempo_medio_segundos']),
            ], array_slice($comMovimento, 0, 5)),
            'horas_com_movimento' => count($comMovimento),
            'periodo' => $this->rotuloDoPeriodo($inicio, $fim),
        ];
    }

    /** @return array<string, mixed> */
    private function abandono(CarbonImmutable $inicio, CarbonImmutable $fim): array
    {
        $m = $this->analytics->taxaDeAbandono($this->restauranteId, $inicio, $fim);

        return [
            'total_entradas' => $m['total_entradas'],
            'abandonos' => $m['abandonos'],
            'desistiu' => $m['desistiu'],
            'expirado' => $m['expirado'],
            'atendido' => $m['atendido'],
            'taxa_percentual' => $m['taxa_percentual'],
            'periodo' => $this->rotuloDoPeriodo($inicio, $fim),
        ];
    }

    /**
     * Da série diária, média e extremos. Os 90 itens repetem 'mesas' e
     * 'segundos_disponiveis' idênticos — ruído puro para o modelo.
     *
     * @return array<string, mixed>
     */
    private function ocupacao(CarbonImmutable $inicio, CarbonImmutable $fim): array
    {
        $serie = $this->analytics->taxaDeOcupacao($this->restauranteId, $inicio, $fim);

        if ($serie === []) {
            return ['dias' => 0, 'periodo' => $this->rotuloDoPeriodo($inicio, $fim)];
        }

        $taxas = array_column($serie, 'taxa_percentual');
        $melhor = $serie[array_search(max($taxas), $taxas, true)];
        $pior = $serie[array_search(min($taxas), $taxas, true)];

        return [
            'dias' => count($serie),
            'ocupacao_media_percentual' => round(array_sum($taxas) / count($taxas), 2),
            'melhor_dia' => ['data' => $melhor['data'], 'percentual' => $melhor['taxa_percentual']],
            'pior_dia' => ['data' => $pior['data'], 'percentual' => $pior['taxa_percentual']],
            'mesas' => $serie[0]['mesas'],
            'periodo' => $this->rotuloDoPeriodo($inicio, $fim),
        ];
    }

    /**
     * Totais mais as cinco mesas que mais giraram. O 'mesa_id' é UUID de 36
     * caracteres e não diz nada ao modelo — o número da mesa diz.
     *
     * @return array<string, mixed>
     */
    private function giroDeMesa(CarbonImmutable $inicio, CarbonImmutable $fim): array
    {
        $g = $this->analytics->giroDeMesa($this->restauranteId, $inicio, $fim);

        $mesas = $g['mesas'];
        usort($mesas, fn (array $a, array $b) => $b['atendimentos'] <=> $a['atendimentos']);

        return [
            'total_atendimentos' => $g['total_atendimentos'],
            'permanencia_media_min' => $this->emMinutos($g['duracao_media_geral_segundos']),
            'giro_medio_por_mesa' => $g['giro_medio_por_mesa'],
            'total_de_mesas' => count($g['mesas']),
            'mais_giraram' => array_map(fn (array $m) => [
                'mesa' => $m['numero'],
                'lugares' => $m['capacidade'],
                'atendimentos' => $m['atendimentos'],
                'permanencia_media_min' => $this->emMinutos($m['duracao_media_segundos']),
            ], array_slice($mesas, 0, 5)),
            'periodo' => $this->rotuloDoPeriodo($inicio, $fim),
        ];
    }

    /** @return array<string, mixed> */
    private function desperdicio(CarbonImmutable $inicio, CarbonImmutable $fim): array
    {
        $m = $this->analytics->desperdicioDeCapacidade($this->restauranteId, $inicio, $fim);

        return [
            'alocacoes' => $m['total_alocacoes'],
            'lugares_ociosos' => $m['lugares_ociosos'],
            'desperdicio_medio_por_mesa' => $m['desperdicio_medio_por_alocacao'],
            'ociosidade_percentual' => $m['taxa_de_ociosidade_percentual'],
            'periodo' => $this->rotuloDoPeriodo($inicio, $fim),
        ];
    }

    /** @return array<string, mixed> */
    private function limpeza(CarbonImmutable $inicio, CarbonImmutable $fim): array
    {
        $m = $this->analytics->temposDeLimpeza($this->restauranteId, $inicio, $fim);

        return [
            'ciclos' => $m['ciclos'],
            'espera_media_min' => $this->emMinutos($m['espera_media_segundos']),
            'limpeza_media_min' => $this->emMinutos($m['limpeza_media_segundos']),
            'total_medio_min' => $this->emMinutos($m['total_medio_segundos']),
            // Ciclo liberado pelo comando ficou fora das medias; a proporcao diz
            // se o numero e confiavel ou se a equipe simplesmente nao marca.
            'liberados_automaticamente_percentual' => $m['taxa_automatica_percentual'],
            'periodo' => $this->rotuloDoPeriodo($inicio, $fim),
        ];
    }

    // ───────────────────── Apoio ─────────────────────

    /**
     * Resolve o período dos argumentos, com o mesmo padrão do AnalyticsController.
     *
     * Data em formato impossível vira InvalidArgumentException com mensagem que o
     * modelo consegue ler e corrigir, em vez de erro de parsing do Carbon.
     *
     * @param  array<string, mixed>  $argumentos
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function periodo(array $argumentos): array
    {
        $fim = $this->dia($argumentos['data_fim'] ?? null)
            ?? CarbonImmutable::now('America/Sao_Paulo');

        $inicio = $this->dia($argumentos['data_inicio'] ?? null)
            ?? $fim->subDays($this->diasPadrao() - 1);

        return [$inicio->startOfDay(), $fim->endOfDay()];
    }

    private function dia(mixed $valor): ?CarbonImmutable
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse(trim($valor), 'America/Sao_Paulo');
        } catch (\Throwable) {
            throw new InvalidArgumentException(
                "Data '{$valor}' não foi entendida. Use o formato AAAA-MM-DD."
            );
        }
    }

    private function rotuloDoPeriodo(CarbonImmutable $inicio, CarbonImmutable $fim): string
    {
        return $inicio->format('d/m/Y').' a '.$fim->format('d/m/Y');
    }

    private function emMinutos(float|int|null $segundos): float
    {
        return round(((float) $segundos) / 60, 1);
    }

    private function diasPadrao(): int
    {
        return (int) config('assistente.dias_padrao');
    }
}
