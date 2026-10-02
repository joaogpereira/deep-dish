<?php

namespace App\Services;

use App\Models\ClienteFila;
use App\Models\Restaurante;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Agregados históricos de fila e ocupação de um restaurante.
 *
 * ── Por que tudo aqui usa DB::table e não Eloquent ──
 * 'clientefila' e 'clientemesa' usam SoftDeletes, e nos dois casos o registro
 * apagado É o histórico:
 *
 *   - ClienteFila::registrarSaida() faz soft delete ao encerrar a entrada, ou
 *     seja, TODA linha concluída está com deleted_at preenchido.
 *   - ClienteMesa ganhou soft delete (migration 2026_08_16_193000) justamente
 *     para que ReservaController::forceDestroyRestaurante não destrua a
 *     permanência já registrada.
 *
 * DB::table ignora o escopo de soft delete por construção, que é exatamente o
 * comportamento desejado para histórico. Se algum método for reescrito em
 * Eloquent, ele precisa de ->withTrashed() — sem isso o número vira zero sem
 * erro nenhum, que é a falha mais difícil de perceber nesta classe.
 *
 * ── Fuso ──
 * As colunas guardam UTC (config/app.php: 'timezone' => 'UTC'), mas o
 * restaurante opera em horário de Brasília. Agrupar hora-do-dia direto do UTC
 * jogaria os picos de almoço e jantar três horas para trás no gráfico. Toda
 * extração de dia-da-semana e hora passa por emFusoLocal(), e os limites do
 * período usam a MESMA expressão — filtro e agrupamento precisam concordar,
 * senão os dias da borda entram pela metade.
 *
 * ── Contrato de saída ──
 * Todo método devolve estrutura completa mesmo sem dado: os sete dias da
 * semana, as 24 horas, as 168 células do mapa, todos os dias do período, todas
 * as mesas do restaurante. O esqueleto é montado em PHP e preenchido pelo
 * agregado do banco — nunca há divisão por zero e o gráfico nunca recebe série
 * com buraco.
 *
 * ── Sprint 4 ──
 * As assinaturas destes métodos viram tools do assistente (#178). Por isso cada
 * um tem parâmetros achatados e tipados, retorno serializável em array e
 * docblock em português que começa dizendo QUAL PERGUNTA ele responde — esse
 * texto vira a descrição da ferramenta.
 */
class AnalyticsService
{
    /** Fuso em que o restaurante opera; as colunas guardam UTC. */
    private const FUSO = 'America/Sao_Paulo';

    /** Usados quando o restaurante não tem horário cadastrado. */
    private const ABERTURA_PADRAO = '11:00';

    private const FECHAMENTO_PADRAO = '23:00';

    /** Teto de dias por consulta — evita varrer anos por engano. */
    private const MAX_DIAS = 366;

    private const NOMES_DOS_DIAS = [
        0 => 'domingo',
        1 => 'segunda-feira',
        2 => 'terça-feira',
        3 => 'quarta-feira',
        4 => 'quinta-feira',
        5 => 'sexta-feira',
        6 => 'sábado',
    ];

    /**
     * Responde: "em que dia da semana meu cliente espera mais?".
     *
     * Tempo médio de espera na fila, quebrado por dia da semana no fuso do
     * restaurante. Devolve sempre os sete dias, inclusive os sem movimento.
     *
     * Há duas médias de propósito: 'tempo_medio_segundos' inclui quem desistiu
     * — a espera dessa pessoa foi real e é justamente o que explica a
     * desistência — e 'tempo_medio_atendidos_segundos' considera só quem chegou
     * a sentar, respondendo "quanto tempo até ser chamado".
     *
     * @return list<array{dia_da_semana:int, nome:string, entradas:int, tempo_medio_segundos:float, tempo_medio_atendidos_segundos:float}>
     */
    public function tempoMedioDeEsperaPorDiaDaSemana(
        string $restauranteId,
        CarbonInterface $inicio,
        CarbonInterface $fim
    ): array {
        $expressao = $this->emFusoLocal('clientefila.created_at');

        $linhas = $this->entradasDeFila($restauranteId, $inicio, $fim)
            ->whereNotNull('clientefila.tempo_espera_segundos')
            ->selectRaw("EXTRACT(DOW FROM {$expressao})::int AS chave")
            ->selectRaw('COUNT(*) AS entradas')
            ->selectRaw('AVG(clientefila.tempo_espera_segundos) AS media_geral')
            ->selectRaw(
                'AVG(clientefila.tempo_espera_segundos) FILTER (WHERE clientefila.status_saida = ?) AS media_atendidos',
                [ClienteFila::STATUS_SAIDA_ATENDIDO]
            )
            ->groupByRaw("EXTRACT(DOW FROM {$expressao})")
            ->get()
            ->keyBy('chave');

        return array_map(function (int $dia) use ($linhas): array {
            $linha = $linhas->get($dia);

            return [
                'dia_da_semana' => $dia,
                'nome' => self::NOMES_DOS_DIAS[$dia],
                'entradas' => (int) ($linha->entradas ?? 0),
                'tempo_medio_segundos' => round((float) ($linha->media_geral ?? 0), 1),
                'tempo_medio_atendidos_segundos' => round((float) ($linha->media_atendidos ?? 0), 1),
            ];
        }, range(0, 6));
    }

    /**
     * Responde: "qual horário do dia tem a pior espera?".
     *
     * Tempo médio de espera na fila por hora do dia (0 a 23), no fuso do
     * restaurante. Devolve sempre as 24 horas. As duas médias têm o mesmo
     * significado descrito em tempoMedioDeEsperaPorDiaDaSemana().
     *
     * @return list<array{hora:int, entradas:int, tempo_medio_segundos:float, tempo_medio_atendidos_segundos:float}>
     */
    public function tempoMedioDeEsperaPorFaixaHoraria(
        string $restauranteId,
        CarbonInterface $inicio,
        CarbonInterface $fim
    ): array {
        $expressao = $this->emFusoLocal('clientefila.created_at');

        $linhas = $this->entradasDeFila($restauranteId, $inicio, $fim)
            ->whereNotNull('clientefila.tempo_espera_segundos')
            ->selectRaw("EXTRACT(HOUR FROM {$expressao})::int AS chave")
            ->selectRaw('COUNT(*) AS entradas')
            ->selectRaw('AVG(clientefila.tempo_espera_segundos) AS media_geral')
            ->selectRaw(
                'AVG(clientefila.tempo_espera_segundos) FILTER (WHERE clientefila.status_saida = ?) AS media_atendidos',
                [ClienteFila::STATUS_SAIDA_ATENDIDO]
            )
            ->groupByRaw("EXTRACT(HOUR FROM {$expressao})")
            ->get()
            ->keyBy('chave');

        return array_map(function (int $hora) use ($linhas): array {
            $linha = $linhas->get($hora);

            return [
                'hora' => $hora,
                'entradas' => (int) ($linha->entradas ?? 0),
                'tempo_medio_segundos' => round((float) ($linha->media_geral ?? 0), 1),
                'tempo_medio_atendidos_segundos' => round((float) ($linha->media_atendidos ?? 0), 1),
            ];
        }, range(0, 23));
    }

    /**
     * Responde: "que fração de quem entrou na fila foi embora sem ser atendido?".
     *
     * Abandono = 'desistiu' (saiu por conta própria) + 'expirado' (foi chamado e
     * não apareceu), sobre o total de entradas do período. 'removido' é ação
     * administrativa do restaurante e NÃO conta como abandono.
     *
     * Entradas ainda ativas entram no denominador, porque o total é o total —
     * mas vêm separadas em 'ativas' para que um período recente, cheio de fila
     * em andamento, possa ser lido com o desconto na mão.
     *
     * @return array{total_entradas:int, abandonos:int, desistiu:int, expirado:int, atendido:int, removido:int, ativas:int, taxa:float, taxa_percentual:float}
     */
    public function taxaDeAbandono(
        string $restauranteId,
        CarbonInterface $inicio,
        CarbonInterface $fim
    ): array {
        $linha = $this->entradasDeFila($restauranteId, $inicio, $fim)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(*) FILTER (WHERE clientefila.status_saida IS NULL) AS ativas')
            ->selectRaw('COUNT(*) FILTER (WHERE clientefila.status_saida = ?) AS desistiu', [ClienteFila::STATUS_SAIDA_DESISTIU])
            ->selectRaw('COUNT(*) FILTER (WHERE clientefila.status_saida = ?) AS expirado', [ClienteFila::STATUS_SAIDA_EXPIRADO])
            ->selectRaw('COUNT(*) FILTER (WHERE clientefila.status_saida = ?) AS atendido', [ClienteFila::STATUS_SAIDA_ATENDIDO])
            ->selectRaw('COUNT(*) FILTER (WHERE clientefila.status_saida = ?) AS removido', [ClienteFila::STATUS_SAIDA_REMOVIDO])
            ->first();

        $total = (int) ($linha->total ?? 0);
        $desistiu = (int) ($linha->desistiu ?? 0);
        $expirado = (int) ($linha->expirado ?? 0);
        $abandonos = $desistiu + $expirado;

        $taxa = $total > 0 ? $abandonos / $total : 0.0;

        return [
            'total_entradas' => $total,
            'abandonos' => $abandonos,
            'desistiu' => $desistiu,
            'expirado' => $expirado,
            'atendido' => (int) ($linha->atendido ?? 0),
            'removido' => (int) ($linha->removido ?? 0),
            'ativas' => (int) ($linha->ativas ?? 0),
            'taxa' => round($taxa, 4),
            'taxa_percentual' => round($taxa * 100, 2),
        ];
    }

    /**
     * Responde: quanto lugar sobra nas mesas que a fila alocou. Mede a qualidade
     * do encaixe entre grupo e mesa (issue #169) — desperdicio de uma alocacao e
     * 'capacidade da mesa menos tamanho do grupo'.
     *
     * So conta alocacao feita pelo algoritmo. 'clientemesa' tem duas origens: a
     * promocao da fila e a reserva que o proprio cliente escolheu clicando numa
     * mesa. A segunda nao e decisao do algoritmo e poluiria a metrica; o join por
     * 'clientefila.clientemesa_id' (escrito em ClienteFila::registrarChamada) e o
     * que separa as duas, porque so a promocao preenche essa coluna.
     *
     * O periodo filtra pela ENTRADA na fila, nao pelo instante da alocacao, para
     * ficar coerente com as outras metricas de fila desta classe.
     */
    public function desperdicioDeCapacidade(
        string $restauranteId,
        CarbonInterface $inicio,
        CarbonInterface $fim
    ): array {
        $linha = $this->entradasDeFila($restauranteId, $inicio, $fim)
            ->join('clientemesa', 'clientemesa.id', '=', 'clientefila.clientemesa_id')
            ->join('mesa', 'mesa.id', '=', 'clientemesa.mesa_id')
            ->selectRaw('COUNT(*) AS alocacoes')
            ->selectRaw('COALESCE(SUM(mesa.capacidade), 0) AS ofertados')
            ->selectRaw('COALESCE(SUM(clientemesa.party_size), 0) AS ocupados')
            ->selectRaw('COALESCE(SUM(mesa.capacidade - clientemesa.party_size), 0) AS ociosos')
            ->first();

        $alocacoes = (int) ($linha->alocacoes ?? 0);
        $ofertados = (int) ($linha->ofertados ?? 0);
        $ociosos = (int) ($linha->ociosos ?? 0);

        $taxa = $ofertados > 0 ? $ociosos / $ofertados : 0.0;

        return [
            'total_alocacoes' => $alocacoes,
            'lugares_ofertados' => $ofertados,
            'lugares_ocupados' => (int) ($linha->ocupados ?? 0),
            'lugares_ociosos' => $ociosos,
            'desperdicio_medio_por_alocacao' => $alocacoes > 0 ? round($ociosos / $alocacoes, 1) : 0.0,
            'taxa_de_ociosidade' => round($taxa, 4),
            'taxa_de_ociosidade_percentual' => round($taxa * 100, 2),
        ];
    }

    /**
     * Responde: "quanto da minha capacidade instalada rendeu em cada dia?".
     *
     * Taxa de ocupação diária = horas-mesa ocupadas ÷ horas-mesa disponíveis,
     * onde o disponível é (mesas do restaurante × janela de funcionamento do
     * dia). Só entra permanência de quem fez check-in: 'duracao_segundos' é
     * NULL para no-show de propósito, para não rebaixar a média.
     *
     * A série cobre todos os dias do período, inclusive os sem movimento.
     *
     * Limitação conhecida: o número de mesas usado é o ATUAL, não o que existia
     * na data. Um restaurante que mudou o salão no meio do período terá a taxa
     * dos dias antigos distorcida.
     *
     * @return list<array{data:string, mesas:int, segundos_disponiveis:int, segundos_ocupados:int, atendimentos:int, taxa:float, taxa_percentual:float}>
     */
    public function taxaDeOcupacao(
        string $restauranteId,
        CarbonInterface $inicio,
        CarbonInterface $fim
    ): array {
        $dias = $this->diasDoPeriodo($inicio, $fim);

        $mesas = $this->totalDeMesas($restauranteId);
        $disponivel = $mesas * $this->janelaDeFuncionamentoEmSegundos($this->restaurante($restauranteId));

        $expressao = $this->emFusoLocal('clientemesa.horario_reserva');

        $linhas = $this->reservas($restauranteId, $inicio, $fim)
            ->selectRaw("to_char(date_trunc('day', {$expressao}), 'YYYY-MM-DD') AS chave")
            ->selectRaw('COALESCE(SUM(clientemesa.duracao_segundos), 0) AS ocupados')
            ->selectRaw('COUNT(*) FILTER (WHERE clientemesa.horario_checkin IS NOT NULL) AS atendimentos')
            ->groupByRaw("date_trunc('day', {$expressao})")
            ->get()
            ->keyBy('chave');

        return array_map(function (string $data) use ($linhas, $mesas, $disponivel): array {
            $linha = $linhas->get($data);
            $ocupados = (int) ($linha->ocupados ?? 0);

            // Restaurante sem mesa cadastrada zera o denominador; a taxa vira 0
            // em vez de estourar.
            $taxa = $disponivel > 0 ? $ocupados / $disponivel : 0.0;

            return [
                'data' => $data,
                'mesas' => $mesas,
                'segundos_disponiveis' => $disponivel,
                'segundos_ocupados' => $ocupados,
                'atendimentos' => (int) ($linha->atendimentos ?? 0),
                'taxa' => round($taxa, 4),
                'taxa_percentual' => round($taxa * 100, 2),
            ];
        }, $dias);
    }

    /**
     * Responde: "quais mesas mais giram, e quanto tempo o cliente fica sentado?".
     *
     * Giro é a contagem de atendimentos com check-in no período. A permanência
     * média ignora quem não sentou (duracao_segundos NULL), então uma mesa com
     * muitos no-shows aparece com poucos atendimentos — não com permanência
     * artificialmente baixa. Todas as mesas do restaurante aparecem, inclusive
     * as que não giraram nenhuma vez.
     *
     * @return array{mesas:list<array{mesa_id:string, numero:int|null, capacidade:int|null, atendimentos:int, duracao_media_segundos:float}>, total_atendimentos:int, duracao_media_geral_segundos:float, giro_medio_por_mesa:float}
     */
    public function giroDeMesa(
        string $restauranteId,
        CarbonInterface $inicio,
        CarbonInterface $fim
    ): array {
        $this->validarPeriodo($inicio, $fim);

        $expressao = $this->emFusoLocal('clientemesa.horario_reserva');

        // O filtro de período vive no ON do LEFT JOIN, não no WHERE: no WHERE
        // ele eliminaria as mesas sem atendimento no período, que são
        // justamente as que o gestor precisa ver.
        $linhas = DB::table('mesa')
            ->leftJoin('clientemesa', function ($join) use ($expressao, $inicio, $fim) {
                $join->on('clientemesa.mesa_id', '=', 'mesa.id')
                    ->whereRaw("{$expressao} >= ?", [$this->limite($inicio)])
                    ->whereRaw("{$expressao} <= ?", [$this->limite($fim)]);
            })
            ->where('mesa.restaurante_id', $restauranteId)
            ->groupBy('mesa.id', 'mesa.numero', 'mesa.capacidade')
            ->orderBy('mesa.numero')
            ->selectRaw('mesa.id, mesa.numero, mesa.capacidade')
            ->selectRaw('COUNT(clientemesa.id) FILTER (WHERE clientemesa.horario_checkin IS NOT NULL) AS atendimentos')
            ->selectRaw('AVG(clientemesa.duracao_segundos) AS duracao_media')
            ->get();

        $mesas = $linhas->map(fn ($linha): array => [
            'mesa_id' => (string) $linha->id,
            'numero' => $linha->numero !== null ? (int) $linha->numero : null,
            'capacidade' => $linha->capacidade !== null ? (int) $linha->capacidade : null,
            'atendimentos' => (int) $linha->atendimentos,
            'duracao_media_segundos' => round((float) ($linha->duracao_media ?? 0), 1),
        ])->all();

        $totalAtendimentos = array_sum(array_column($mesas, 'atendimentos'));

        // A média geral vem do banco, não da média das médias por mesa: uma mesa
        // com um atendimento só pesaria igual a uma com cinquenta.
        $geral = $this->reservas($restauranteId, $inicio, $fim)
            ->whereNotNull('clientemesa.duracao_segundos')
            ->selectRaw('AVG(clientemesa.duracao_segundos) AS media')
            ->first();

        return [
            'mesas' => $mesas,
            'total_atendimentos' => $totalAtendimentos,
            'duracao_media_geral_segundos' => round((float) ($geral->media ?? 0), 1),
            'giro_medio_por_mesa' => count($mesas) > 0
                ? round($totalAtendimentos / count($mesas), 2)
                : 0.0,
        ];
    }

    /**
     * Responde: "em que combinação de dia da semana e horário minha casa enche?".
     *
     * Mapa de calor do volume de entradas na fila. Devolve a grade completa de
     * 168 células (7 dias × 24 horas) preenchida com zero onde não houve
     * movimento — mapa de calor com célula faltando fica ilegível.
     *
     * 'entradas' conta os grupos; 'pessoas' soma o tamanho deles. 'pico' é a
     * célula de maior volume, ou null quando não houve nenhuma entrada.
     *
     * @return array{celulas:list<array{dia_da_semana:int, nome:string, hora:int, entradas:int, pessoas:int}>, pico:array{dia_da_semana:int, nome:string, hora:int, entradas:int, pessoas:int}|null}
     */
    public function mapaDeCalorDeDemanda(
        string $restauranteId,
        CarbonInterface $inicio,
        CarbonInterface $fim
    ): array {
        $expressao = $this->emFusoLocal('clientefila.created_at');

        $linhas = $this->entradasDeFila($restauranteId, $inicio, $fim)
            ->selectRaw("EXTRACT(DOW FROM {$expressao})::int AS dia")
            ->selectRaw("EXTRACT(HOUR FROM {$expressao})::int AS hora")
            ->selectRaw('COUNT(*) AS entradas')
            ->selectRaw('COALESCE(SUM(clientefila.qntd_pessoas), 0) AS pessoas')
            ->groupByRaw("EXTRACT(DOW FROM {$expressao}), EXTRACT(HOUR FROM {$expressao})")
            ->get()
            ->keyBy(fn ($linha): string => $linha->dia.':'.$linha->hora);

        $celulas = [];
        $pico = null;

        foreach (range(0, 6) as $dia) {
            foreach (range(0, 23) as $hora) {
                $linha = $linhas->get($dia.':'.$hora);

                $celula = [
                    'dia_da_semana' => $dia,
                    'nome' => self::NOMES_DOS_DIAS[$dia],
                    'hora' => $hora,
                    'entradas' => (int) ($linha->entradas ?? 0),
                    'pessoas' => (int) ($linha->pessoas ?? 0),
                ];

                $celulas[] = $celula;

                if ($celula['entradas'] > 0 && ($pico === null || $celula['entradas'] > $pico['entradas'])) {
                    $pico = $celula;
                }
            }
        }

        return ['celulas' => $celulas, 'pico' => $pico];
    }

    /**
     * Responde: "esse gráfico está mostrando uso real ou dado gerado para demonstração?".
     *
     * A coluna 'origem' é NULL para dado produzido pelo uso da aplicação e
     * 'sintetico' para o que veio do HistoricoSinteticoSeeder. Nenhum outro
     * método desta classe filtra por origem — todos agregam tudo. Este existe
     * para que a apresentação consiga declarar o que está plotando, como pede o
     * critério de aceite do seeder de histórico sintético.
     *
     * @return array{fila:array{real:int, sintetico:int}, reservas:array{real:int, sintetico:int}, contem_sintetico:bool}
     */
    public function procedenciaDoHistorico(
        string $restauranteId,
        CarbonInterface $inicio,
        CarbonInterface $fim
    ): array {
        $fila = $this->entradasDeFila($restauranteId, $inicio, $fim)
            ->selectRaw('COUNT(*) FILTER (WHERE clientefila.origem IS NULL) AS reais')
            ->selectRaw('COUNT(*) FILTER (WHERE clientefila.origem IS NOT NULL) AS sinteticos')
            ->first();

        $reservas = $this->reservas($restauranteId, $inicio, $fim)
            ->selectRaw('COUNT(*) FILTER (WHERE clientemesa.origem IS NULL) AS reais')
            ->selectRaw('COUNT(*) FILTER (WHERE clientemesa.origem IS NOT NULL) AS sinteticos')
            ->first();

        $filaSintetico = (int) ($fila->sinteticos ?? 0);
        $reservasSintetico = (int) ($reservas->sinteticos ?? 0);

        return [
            'fila' => [
                'real' => (int) ($fila->reais ?? 0),
                'sintetico' => $filaSintetico,
            ],
            'reservas' => [
                'real' => (int) ($reservas->reais ?? 0),
                'sintetico' => $reservasSintetico,
            ],
            'contem_sintetico' => $filaSintetico + $reservasSintetico > 0,
        ];
    }

    // ───────────────────────── Infraestrutura ─────────────────────────

    /**
     * Entradas de fila do restaurante no período.
     *
     * O join com 'fila' é o que amarra a consulta ao restaurante — 'clientefila'
     * não tem restaurante_id próprio. Sem ele o agregado varreria a base
     * inteira, então este método é o ponto único que sustenta o critério de
     * "nenhum método vaza dado de outro restaurante".
     */
    private function entradasDeFila(string $restauranteId, CarbonInterface $inicio, CarbonInterface $fim): Builder
    {
        return $this->noPeriodo(
            DB::table('clientefila')
                ->join('fila', 'fila.id', '=', 'clientefila.fila_id')
                ->where('fila.restaurante_id', $restauranteId),
            $this->emFusoLocal('clientefila.created_at'),
            $inicio,
            $fim
        );
    }

    /** Reservas e atendimentos do restaurante no período, amarrados via 'mesa'. */
    private function reservas(string $restauranteId, CarbonInterface $inicio, CarbonInterface $fim): Builder
    {
        return $this->noPeriodo(
            DB::table('clientemesa')
                ->join('mesa', 'mesa.id', '=', 'clientemesa.mesa_id')
                ->where('mesa.restaurante_id', $restauranteId),
            $this->emFusoLocal('clientemesa.horario_reserva'),
            $inicio,
            $fim
        );
    }

    private function noPeriodo(Builder $query, string $expressao, CarbonInterface $inicio, CarbonInterface $fim): Builder
    {
        $this->validarPeriodo($inicio, $fim);

        return $query
            ->whereRaw("{$expressao} >= ?", [$this->limite($inicio)])
            ->whereRaw("{$expressao} <= ?", [$this->limite($fim)]);
    }

    private function validarPeriodo(CarbonInterface $inicio, CarbonInterface $fim): void
    {
        if ($inicio->greaterThan($fim)) {
            throw new InvalidArgumentException('A data inicial do período não pode ser posterior à final.');
        }

        // Dia de calendário contra dia de calendário, NO FUSO DO RESTAURANTE.
        //
        // Duas coisas dependem disso. Primeiro, diffInDays() devolve float: de
        // startOfDay até endOfDay o resultado é 365,999..., e o '+1' passava de
        // MAX_DIAS por uma fração, rejeitando exatamente o período máximo que
        // esta guarda deveria permitir. Segundo, o setTimezone tem de estar aqui
        // porque diasDoPeriodo() enumera em self::FUSO — contar em um fuso e
        // enumerar em outro faz a série responder um dia diferente do pedido.
        $diasCorridos = (int) $inicio->copy()->setTimezone(self::FUSO)->startOfDay()
            ->diffInDays($fim->copy()->setTimezone(self::FUSO)->startOfDay());

        if ($diasCorridos + 1 > self::MAX_DIAS) {
            throw new InvalidArgumentException('Período de no máximo '.self::MAX_DIAS.' dias por consulta.');
        }
    }

    /**
     * Converte a coluna UTC para o fuso do restaurante.
     *
     * A constante é interpolada em vez de virar binding porque AT TIME ZONE
     * espera um literal, não um parâmetro — e o valor é uma const da classe,
     * nunca entrada de usuário.
     */
    private function emFusoLocal(string $coluna): string
    {
        return "({$coluna} AT TIME ZONE 'UTC' AT TIME ZONE '".self::FUSO."')";
    }

    /** Limite do período como hora de parede no fuso do restaurante. */
    private function limite(CarbonInterface $instante): string
    {
        return $instante->copy()->setTimezone(self::FUSO)->format('Y-m-d H:i:s');
    }

    /**
     * Dias do período em 'Y-m-d', esqueleto das séries diárias.
     *
     * @return list<string>
     */
    private function diasDoPeriodo(CarbonInterface $inicio, CarbonInterface $fim): array
    {
        $this->validarPeriodo($inicio, $fim);

        $cursor = $inicio->copy()->setTimezone(self::FUSO)->startOfDay();
        $ultimo = $fim->copy()->setTimezone(self::FUSO)->startOfDay();

        $dias = [];

        while ($cursor->lessThanOrEqualTo($ultimo)) {
            $dias[] = $cursor->format('Y-m-d');
            $cursor = $cursor->addDay();
        }

        return $dias;
    }

    private function restaurante(string $restauranteId): Restaurante
    {
        return Restaurante::findOrFail($restauranteId);
    }

    private function totalDeMesas(string $restauranteId): int
    {
        return DB::table('mesa')->where('restaurante_id', $restauranteId)->count();
    }

    /**
     * Janela diária de funcionamento, em segundos.
     *
     * Fechamento menor ou igual à abertura significa virada de meia-noite; a
     * janela é truncada no fim do dia, exatamente como
     * HistoricoSinteticoSeeder::janelaDeFuncionamento() faz na geração. Se as
     * duas regras divergirem, o denominador da ocupação deixa de casar com o
     * dado que o seeder produziu.
     */
    private function janelaDeFuncionamentoEmSegundos(Restaurante $restaurante): int
    {
        $paraMinutos = static function (?string $hora, string $padrao): int {
            [$h, $m] = array_map('intval', explode(':', substr($hora ?: $padrao, 0, 5)));

            return $h * 60 + $m;
        };

        $abertura = $paraMinutos($restaurante->horario_abertura, self::ABERTURA_PADRAO);
        $fechamento = $paraMinutos($restaurante->horario_fechamento, self::FECHAMENTO_PADRAO);

        if ($fechamento <= $abertura) {
            $fechamento = 24 * 60;
        }

        return ($fechamento - $abertura) * 60;
    }
}
