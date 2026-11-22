<?php

namespace App\Services;

use App\Models\ClienteFila;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Estimativa de quanto tempo um cliente vai esperar na fila.
 *
 * ── O que é, e o que NÃO é ──
 * Isto é uma HEURÍSTICA ESTATÍSTICA, não aprendizado de máquina. O modelo é uma
 * regressão linear de mínimos quadrados, `espera ≈ a + b · posição`, calculada
 * pelo próprio Postgres com os agregados regr_intercept(), regr_slope() e
 * regr_count(). Não há treino, pesos, features aprendidas nem modelo
 * persistido: cada chamada recalcula a reta sobre o histórico disponível.
 *
 * A leitura dos coeficientes é direta e é o que torna o número defensável:
 *   'a' = custo fixo, o tempo até a primeira mesa vagar naquele horário;
 *   'b' = minuto marginal por pessoa à frente na fila.
 *
 * A escolha por heurística em vez de ML é deliberada e está registrada no
 * backlog (docs/backlog-pi4.md): doze semanas de operação não geram volume para
 * treinar um modelo que supere a média condicionada.
 *
 * ── Por que a amostra é só quem foi ATENDIDO ──
 * Quem desistiu produz dado censurado: sabemos que esperou ao menos X e foi
 * embora, nunca quanto teria esperado até sentar. Incluir essas linhas puxa a
 * média para baixo justamente nos horários ruins — que são onde mais gente
 * desiste e onde o número precisa ser honesto. Uma fila em que quatro pessoas
 * sentaram após 25 min e seis desistiram após 8 min tem média geral de 15 min,
 * um valor em que ninguém foi atendido.
 *
 * ── Posição histórica e a priorização por encaixe (#170) ──
 * A posição de cada amostra é "quantas pessoas do restaurante estavam na fila,
 * à minha frente, quando cheguei" — a mesma noção de ClienteFila::posicao (todas
 * as filas abertas do restaurante, por chegada). Antes era o ROW_NUMBER dentro
 * do fila_id, que reiniciava a contagem a cada janela de horário e deixava a
 * reta treinada numa escala diferente da posição que a tela passa a estimar.
 *
 * O encaixe não exige outro modelo: quem foi ultrapassado esperou mais do que a
 * posição sugeria, e isso entra nos dados reais que a reta ajusta. O que
 * importa é a escala da posição ser a mesma na amostra e na consulta. A espera
 * de quem é protegido (#170) tem teto prático; já a de quem pode ser
 * ultrapassado é uma estimativa, e a tela a rotula como tal.
 *
 * ── Por que DB::table e não Eloquent ──
 * ClienteFila::registrarSaida() faz soft delete ao encerrar a entrada, então
 * TODA linha concluída tem deleted_at preenchido — e são exatamente essas que
 * formam o histórico. Consulta via Eloquent sem ->withTrashed() devolveria zero
 * sem erro nenhum.
 */
class EstimativaEsperaService
{
    /** Precisa ser o mesmo fuso que o AnalyticsService usa para agrupar. */
    private const FUSO = 'America/Sao_Paulo';

    /** Observações mínimas para um nível ser considerado confiável. */
    private const MIN_AMOSTRA = 20;

    /** Tolerância no tamanho do grupo ao recortar o nível específico. */
    private const TOLERANCIA_GRUPO = 1;

    /** Nível 3: usado quando nenhum histórico serve. Em segundos. */
    private const PADRAO_FIXO_SEGUNDOS = 300;      // 5 min até a primeira mesa

    private const PADRAO_POR_POSICAO_SEGUNDOS = 180; // 3 min por pessoa à frente

    /** Piso do resultado: nunca prometer espera zero. */
    private const MINIMO_SEGUNDOS = 60;

    public const NIVEL_ESPECIFICO = 'especifico';

    public const NIVEL_AMPLO = 'amplo';

    public const NIVEL_PADRAO = 'padrao';

    /**
     * Estima a espera de quem entra na fila na posição informada.
     *
     * Responde: "quanto tempo vou esperar se entrar agora?".
     *
     * Tenta três níveis, do mais específico ao mais genérico, e informa no
     * retorno qual deles sustentou o número — a #164 expõe esse campo para o
     * frontend poder ser honesto sobre a confiança da estimativa.
     *
     * Nunca devolve null e nunca lança: sem histórico algum, cai no padrão
     * declarado nas constantes desta classe.
     *
     * @param  int  $posicao  Posição que o cliente ocupará (1 = próximo a ser chamado).
     * @param  int  $tamanhoGrupo  Quantas pessoas no grupo.
     * @param  CarbonInterface|null  $momento  Instante da entrada; null = agora.
     * @return array{espera_estimada_minutos:int, espera_estimada_segundos:int, nivel:string, amostra:int, posicao:int}
     */
    public function estimar(
        string $restauranteId,
        int $posicao,
        int $tamanhoGrupo,
        ?CarbonInterface $momento = null
    ): array {
        // Posição 0 ou negativa nao existe: quem entra numa fila vazia é o 1º.
        $posicao = max(1, $posicao);

        $momento = ($momento ?? now())->copy()->setTimezone(self::FUSO);

        $niveis = [
            self::NIVEL_ESPECIFICO => fn () => $this->regressao($restauranteId, $momento, $tamanhoGrupo),
            self::NIVEL_AMPLO => fn () => $this->regressao($restauranteId),
        ];

        foreach ($niveis as $nivel => $consulta) {
            $reta = $consulta();

            if ($reta === null) {
                continue;
            }

            $segundos = $reta['intercepto'] + $reta['inclinacao'] * $posicao;

            return $this->resposta($segundos, $nivel, $reta['amostra'], $posicao);
        }

        return $this->resposta(
            self::PADRAO_FIXO_SEGUNDOS + self::PADRAO_POR_POSICAO_SEGUNDOS * $posicao,
            self::NIVEL_PADRAO,
            0,
            $posicao
        );
    }

    /**
     * Ajusta a reta sobre o histórico, opcionalmente recortado pelo contexto.
     *
     * Sem $momento a amostra é o restaurante inteiro (nível amplo). Com ele,
     * recorta por dia da semana, faixa horária e tamanho de grupo parecido
     * (nível específico).
     *
     * Devolve null quando a amostra não sustenta o número — e o chamador cai
     * para o próximo nível. São quatro motivos possíveis:
     *
     *   1. poucas observações (MIN_AMOSTRA);
     *   2. regr_slope() devolve NULL, o que acontece quando todos os registros
     *      estão na MESMA posição — não há reta a traçar com um ponto só de x;
     *   3. inclinação negativa, que diria "quanto mais gente na frente, menos
     *      você espera";
     *   4. intercepto negativo, que produziria estimativa abaixo de zero para
     *      as primeiras posições.
     *
     * Os dois últimos são ruído de amostra pequena, não sinal.
     *
     * @return array{intercepto:float, inclinacao:float, amostra:int}|null
     */
    private function regressao(
        string $restauranteId,
        ?CarbonInterface $momento = null,
        ?int $tamanhoGrupo = null
    ): ?array {
        $local = $this->emFusoLocal('cf.created_at');

        // A posição é contada ANTES de filtrar por 'atendido': quem entrou na
        // fila contou todo mundo à frente, tenha essa gente sentado ou
        // desistido depois. Contar só os atendidos deslocaria toda a escala
        // para baixo e achataria a inclinação.
        //
        // "À frente" = chegou antes, no mesmo restaurante (qualquer fila) e
        // ainda não tinha saído quando esta entrada chegou. O recorte de 1 dia
        // só limita o custo da subconsulta: ninguém espera dias numa fila.
        $sql = "
            WITH numeradas AS (
                SELECT
                    cf.status_saida,
                    cf.tempo_espera_segundos,
                    cf.qntd_pessoas,
                    1 + (
                        SELECT COUNT(*)
                        FROM clientefila a
                        JOIN fila fa ON fa.id = a.fila_id
                        WHERE fa.restaurante_id = f.restaurante_id
                          AND (a.created_at < cf.created_at
                               OR (a.created_at = cf.created_at AND a.id < cf.id))
                          AND a.created_at > cf.created_at - INTERVAL '1 day'
                          AND (a.saiu_em IS NULL OR a.saiu_em > cf.created_at)
                    ) AS posicao,
                    EXTRACT(DOW FROM {$local})::int AS dia,
                    EXTRACT(HOUR FROM {$local})::int AS hora
                FROM clientefila cf
                JOIN fila f ON f.id = cf.fila_id
                WHERE f.restaurante_id = ?
            )
            SELECT
                regr_intercept(tempo_espera_segundos, posicao) AS intercepto,
                regr_slope(tempo_espera_segundos, posicao)     AS inclinacao,
                regr_count(tempo_espera_segundos, posicao)     AS amostra
            FROM numeradas
            WHERE status_saida = ?
              AND tempo_espera_segundos IS NOT NULL
        ";

        $bindings = [$restauranteId, ClienteFila::STATUS_SAIDA_ATENDIDO];

        if ($momento !== null) {
            $sql .= ' AND dia = ? AND hora = ? AND qntd_pessoas BETWEEN ? AND ?';
            $bindings[] = (int) $momento->dayOfWeek;
            $bindings[] = (int) $momento->hour;
            $bindings[] = max(1, ($tamanhoGrupo ?? 1) - self::TOLERANCIA_GRUPO);
            $bindings[] = ($tamanhoGrupo ?? 1) + self::TOLERANCIA_GRUPO;
        }

        $linha = DB::selectOne($sql, $bindings);

        $amostra = (int) ($linha->amostra ?? 0);

        if ($amostra < self::MIN_AMOSTRA || $linha->inclinacao === null || $linha->intercepto === null) {
            return null;
        }

        $inclinacao = (float) $linha->inclinacao;
        $intercepto = (float) $linha->intercepto;

        if ($inclinacao < 0 || $intercepto < 0) {
            return null;
        }

        return [
            'intercepto' => $intercepto,
            'inclinacao' => $inclinacao,
            'amostra' => $amostra,
        ];
    }

    /**
     * @return array{espera_estimada_minutos:int, espera_estimada_segundos:int, nivel:string, amostra:int, posicao:int}
     */
    private function resposta(float $segundos, string $nivel, int $amostra, int $posicao): array
    {
        $segundos = (int) round(max(self::MINIMO_SEGUNDOS, $segundos));

        return [
            // Arredonda para cima: prometer menos do que se entrega irrita mais
            // do que o contrário.
            'espera_estimada_minutos' => (int) ceil($segundos / 60),
            'espera_estimada_segundos' => $segundos,
            'nivel' => $nivel,
            'amostra' => $amostra,
            'posicao' => $posicao,
        ];
    }

    /**
     * Converte a coluna UTC para o fuso do restaurante.
     *
     * As colunas guardam UTC (config/app.php), mas o recorte por dia da semana e
     * faixa horária só faz sentido no horário local — sem isto, o pico de jantar
     * das 20h cairia na janela das 23h e a amostra sairia trocada.
     *
     * A constante é interpolada porque AT TIME ZONE exige literal, e o valor
     * nunca vem de entrada de usuário.
     */
    private function emFusoLocal(string $coluna): string
    {
        return "({$coluna} AT TIME ZONE 'UTC' AT TIME ZONE '".self::FUSO."')";
    }
}
