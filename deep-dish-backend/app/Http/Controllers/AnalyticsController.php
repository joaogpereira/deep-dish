<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Leitura do histórico operacional do restaurante autenticado.
 *
 * Separado do DashboardController de proposito: aquele responde "como esta a
 * casa agora" (quatro contadores instantaneos) e este responde "como foi ate
 * aqui". Sao perguntas diferentes, com custo de consulta diferente, e o
 * frontend atual depende do /dashboard continuar como esta.
 */
class AnalyticsController extends Controller
{
    /**
     * Fuso em que o periodo e interpretado.
     *
     * Precisa ser o MESMO que o AnalyticsService usa para agrupar dia e hora.
     * Se o controller montasse os limites em UTC, o filtro e o agrupamento
     * discordariam e os dias das bordas entrariam pela metade.
     */
    private const FUSO = 'America/Sao_Paulo';

    /** Janela usada quando o cliente nao informa datas. */
    private const DIAS_PADRAO = 90;

    /** Mesmo teto do AnalyticsService — validado aqui para virar 422, nao 500. */
    private const MAX_DIAS = 366;

    public function __construct(private readonly AnalyticsService $analytics) {}

    public function index(Request $request): JsonResponse
    {
        // A ordem entre as datas NAO usa 'after_or_equal:data_inicio': quando o
        // cliente manda so 'data_fim', a regra tenta interpretar a string
        // 'data_inicio' como data e rejeita uma requisicao valida. A comparacao
        // acontece abaixo, ja sobre os limites resolvidos.
        $validado = $request->validate([
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
        ]);

        [$inicio, $fim] = $this->periodo($validado);

        if ($inicio->greaterThan($fim)) {
            return response()->json([
                'error' => 'periodo_invalido',
                'message' => 'A data inicial nao pode ser posterior a final.',
            ], 422);
        }

        // Dia de calendario contra dia de calendario: diffInDays() devolve float,
        // e de startOfDay ate endOfDay o resultado e 89,999..., nao 90. Comparar
        // os dois inicios de dia da o numero inteiro que o periodo cobre.
        $dias = (int) $inicio->startOfDay()->diffInDays($fim->startOfDay()) + 1;

        if ($dias > self::MAX_DIAS) {
            return response()->json([
                'error' => 'periodo_muito_longo',
                'message' => 'Periodo de no maximo '.self::MAX_DIAS.' dias por consulta.',
            ], 422);
        }

        $restauranteId = (string) auth('restaurante')->id();

        try {
            return response()->json([
                // Ecoa o periodo efetivamente usado: sem isto o frontend nao tem
                // como rotular o grafico quando o padrao de 90 dias entrou em acao.
                'periodo' => [
                    'data_inicio' => $inicio->format('Y-m-d'),
                    'data_fim' => $fim->format('Y-m-d'),
                    'dias' => $dias,
                    'fuso' => self::FUSO,
                ],
                'procedencia' => $this->analytics->procedenciaDoHistorico($restauranteId, $inicio, $fim),
                'espera_por_dia_da_semana' => $this->analytics->tempoMedioDeEsperaPorDiaDaSemana($restauranteId, $inicio, $fim),
                'espera_por_faixa_horaria' => $this->analytics->tempoMedioDeEsperaPorFaixaHoraria($restauranteId, $inicio, $fim),
                'abandono' => $this->analytics->taxaDeAbandono($restauranteId, $inicio, $fim),
                'ocupacao' => $this->analytics->taxaDeOcupacao($restauranteId, $inicio, $fim),
                'giro_de_mesa' => $this->analytics->giroDeMesa($restauranteId, $inicio, $fim),
                'mapa_de_calor' => $this->analytics->mapaDeCalorDeDemanda($restauranteId, $inicio, $fim),
                'desperdicio_de_capacidade' => $this->analytics->desperdicioDeCapacidade($restauranteId, $inicio, $fim),
            ]);
        } catch (InvalidArgumentException $e) {
            // Rede de seguranca: as guardas do servico ja foram cobertas acima,
            // mas uma regra nova la nao pode virar 500 aqui.
            return response()->json([
                'error' => 'periodo_invalido',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Resolve o periodo da consulta, sempre em horario local do restaurante.
     *
     * O padrao de 90 dias casa com as ~12 semanas que o 'historico:sintetico'
     * gera, entao o dashboard abre com grafico cheio em vez de pedir ao usuario
     * que escolha uma data antes de ver qualquer coisa.
     *
     * Os limites vao para o inicio e o fim do dia porque o cliente manda data
     * ('2026-08-01'), nao instante: sem o endOfDay o ultimo dia entraria so ate
     * a meia-noite e apareceria vazio no grafico.
     *
     * @param  array{data_inicio?:string|null, data_fim?:string|null}  $validado
     * @return array{0:CarbonImmutable, 1:CarbonImmutable}
     */
    private function periodo(array $validado): array
    {
        $fim = isset($validado['data_fim'])
            ? $this->diaLocal($validado['data_fim'])->endOfDay()
            : CarbonImmutable::now(self::FUSO)->endOfDay();

        $inicio = isset($validado['data_inicio'])
            ? $this->diaLocal($validado['data_inicio'])->startOfDay()
            : $fim->subDays(self::DIAS_PADRAO - 1)->startOfDay();

        return [$inicio, $fim];
    }

    /**
     * Interpreta a entrada como DIA DE CALENDARIO no fuso do restaurante.
     *
     * Hora e fuso que venham na string sao descartados de proposito:
     * '2026-08-01' e '2026-08-01T00:00:00Z' significam o mesmo dia para quem
     * opera a casa, e a alternativa e pior de duas formas.
     *
     * Passar o fuso como 2o argumento do parse() NAO resolve: esse parametro so
     * vale quando a string nao carrega fuso proprio. Com o 'Z' ele e ignorado, o
     * objeto fica em UTC, e o startOfDay()/endOfDay() seguinte recorta o dia
     * errado — o periodo respondido saia deslocado em um dia.
     */
    private function diaLocal(string $valor): CarbonImmutable
    {
        $data = CarbonImmutable::parse($valor)->format('Y-m-d');

        return CarbonImmutable::parse($data, self::FUSO);
    }
}
