<?php

namespace Tests\Feature;

use App\Models\ClienteFila;
use App\Models\ClienteMesa;
use App\Models\Fila;
use App\Models\Mesa;
use App\Models\Restaurante;
use App\Services\AnalyticsService;
use Database\Seeders\HistoricoSinteticoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Cobre os agregados do AnalyticsService (#161).
 *
 * ── Por que o relógio é congelado ──
 * ClienteFila::registrarSaida() e ClienteMesa::registrarSaida() derivam a saída
 * e a duração de now(). Criar uma linha com created_at de três semanas atrás e
 * chamar essas portas produziria saída "hoje" e espera de três semanas. Em vez
 * de escrever as colunas de histórico na mão — o que burlaria o hook `deleting`
 * do ClienteFila — os helpers abaixo movem o relógio, como o
 * HistoricoSinteticoSeeder já faz na geração.
 *
 * ── Por que as datas são fixas ──
 * Os agregados agrupam por dia da semana e hora no fuso de São Paulo. Datas
 * relativas a now() fariam o resultado depender do dia em que a suíte roda.
 * 2026-08-19 é quarta-feira; há uma asserção de guarda para o caso de alguém
 * trocar a data sem perceber.
 */
class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private const FUSO = 'America/Sao_Paulo';

    private AnalyticsService $analytics;

    private Restaurante $restaurante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analytics = app(AnalyticsService::class);

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

    // ─── A) Os números calculados ────────────────────────────

    public function test_tempo_medio_de_espera_por_dia_da_semana_calcula_as_duas_medias(): void
    {
        $quarta = $this->local('2026-08-19 13:00:00');
        $this->assertSame(3, $quarta->dayOfWeek, 'Guarda: 2026-08-19 deixou de ser quarta-feira.');

        $fila = Fila::factory()->for($this->restaurante)->create();

        $this->entrada($fila, $quarta, 10, ClienteFila::STATUS_SAIDA_ATENDIDO);
        $this->entrada($fila, $quarta, 20, ClienteFila::STATUS_SAIDA_ATENDIDO);
        $this->entrada($fila, $quarta, 60, ClienteFila::STATUS_SAIDA_DESISTIU);

        $linhas = collect($this->analytics->tempoMedioDeEsperaPorDiaDaSemana(
            $this->restaurante->id,
            ...$this->agosto()
        ))->keyBy('dia_da_semana');

        $this->assertCount(7, $linhas, 'A série precisa ter os sete dias, com ou sem movimento.');

        $qua = $linhas[3];
        $this->assertSame(3, $qua['entradas']);
        // (600 + 1200 + 3600) / 3
        $this->assertSame(1800.0, $qua['tempo_medio_segundos']);
        // Só os atendidos: (600 + 1200) / 2. A desistência de 60min fica de fora.
        $this->assertSame(900.0, $qua['tempo_medio_atendidos_segundos']);

        $this->assertSame(0, $linhas[1]['entradas']);
        $this->assertSame(0.0, $linhas[1]['tempo_medio_segundos']);
    }

    public function test_tempo_medio_de_espera_por_faixa_horaria_agrupa_na_hora_local(): void
    {
        $fila = Fila::factory()->for($this->restaurante)->create();

        $this->entrada($fila, $this->local('2026-08-19 13:00:00'), 10, ClienteFila::STATUS_SAIDA_ATENDIDO);
        $this->entrada($fila, $this->local('2026-08-19 13:40:00'), 30, ClienteFila::STATUS_SAIDA_ATENDIDO);
        $this->entrada($fila, $this->local('2026-08-19 20:00:00'), 50, ClienteFila::STATUS_SAIDA_ATENDIDO);

        $linhas = collect($this->analytics->tempoMedioDeEsperaPorFaixaHoraria(
            $this->restaurante->id,
            ...$this->agosto()
        ))->keyBy('hora');

        $this->assertCount(24, $linhas);

        $this->assertSame(2, $linhas[13]['entradas']);
        $this->assertSame(1200.0, $linhas[13]['tempo_medio_segundos']); // (600 + 1800) / 2
        $this->assertSame(1, $linhas[20]['entradas']);
        $this->assertSame(3000.0, $linhas[20]['tempo_medio_segundos']);
        $this->assertSame(0, $linhas[3]['entradas']);
    }

    public function test_taxa_de_abandono_soma_desistiu_e_expirado_sobre_o_total(): void
    {
        $quarta = $this->local('2026-08-19 19:00:00');
        $fila = Fila::factory()->for($this->restaurante)->create();

        $this->entrada($fila, $quarta, 40, ClienteFila::STATUS_SAIDA_DESISTIU);
        $this->entrada($fila, $quarta, 35, ClienteFila::STATUS_SAIDA_DESISTIU);
        $this->entrada($fila, $quarta, 25, ClienteFila::STATUS_SAIDA_EXPIRADO);
        $this->entrada($fila, $quarta, 15, ClienteFila::STATUS_SAIDA_ATENDIDO);
        $this->entrada($fila, $quarta, 10, ClienteFila::STATUS_SAIDA_REMOVIDO);

        $taxa = $this->analytics->taxaDeAbandono($this->restaurante->id, ...$this->agosto());

        $this->assertSame(5, $taxa['total_entradas']);
        $this->assertSame(2, $taxa['desistiu']);
        $this->assertSame(1, $taxa['expirado']);
        $this->assertSame(3, $taxa['abandonos']);
        // 'removido' é ação administrativa e não conta como abandono.
        $this->assertSame(1, $taxa['removido']);
        $this->assertSame(1, $taxa['atendido']);
        $this->assertSame(0.6, $taxa['taxa']);
        $this->assertSame(60.0, $taxa['taxa_percentual']);
    }

    public function test_taxa_de_ocupacao_divide_horas_mesa_ocupadas_pela_janela_de_funcionamento(): void
    {
        Mesa::factory()->count(2)->for($this->restaurante)->create();
        $mesa = Mesa::where('restaurante_id', $this->restaurante->id)->first();

        // 11:00–23:00 => janela de 12h. 2 mesas => 86.400 s disponíveis no dia.
        $this->reservaLiberada($mesa, $this->local('2026-08-19 19:00:00'), 60);

        $serie = collect($this->analytics->taxaDeOcupacao(
            $this->restaurante->id,
            ...$this->agosto()
        ))->keyBy('data');

        $this->assertCount(31, $serie, 'A série precisa cobrir todos os dias do período.');

        $dia = $serie['2026-08-19'];
        $this->assertSame(2, $dia['mesas']);
        $this->assertSame(86400, $dia['segundos_disponiveis']);
        $this->assertSame(3600, $dia['segundos_ocupados']);
        $this->assertSame(1, $dia['atendimentos']);
        $this->assertSame(0.0417, $dia['taxa']);

        $vazio = $serie['2026-08-20'];
        $this->assertSame(0, $vazio['segundos_ocupados']);
        $this->assertSame(0.0, $vazio['taxa']);
    }

    public function test_giro_de_mesa_conta_atendimentos_e_inclui_mesa_sem_movimento(): void
    {
        $comGiro = Mesa::factory()->for($this->restaurante)->create(['numero' => 1]);
        $semGiro = Mesa::factory()->for($this->restaurante)->create(['numero' => 2]);

        $this->reservaLiberada($comGiro, $this->local('2026-08-19 12:00:00'), 60);
        $this->reservaLiberada($comGiro, $this->local('2026-08-19 20:00:00'), 90);

        $giro = $this->analytics->giroDeMesa($this->restaurante->id, ...$this->agosto());

        $mesas = collect($giro['mesas'])->keyBy('numero');

        $this->assertCount(2, $mesas, 'Mesa sem atendimento tem de aparecer na lista.');
        $this->assertSame(2, $mesas[1]['atendimentos']);
        $this->assertSame(4500.0, $mesas[1]['duracao_media_segundos']); // (3600 + 5400) / 2
        $this->assertSame(0, $mesas[2]['atendimentos']);
        $this->assertSame(0.0, $mesas[2]['duracao_media_segundos']);

        $this->assertSame(2, $giro['total_atendimentos']);
        $this->assertSame(4500.0, $giro['duracao_media_geral_segundos']);
        $this->assertSame(1.0, $giro['giro_medio_por_mesa']);
    }

    public function test_mapa_de_calor_devolve_a_grade_completa_e_aponta_o_pico(): void
    {
        $fila = Fila::factory()->for($this->restaurante)->create();

        // Sábado 2026-08-22, às 20h: duas entradas, 4 + 2 pessoas.
        $sabado = $this->local('2026-08-22 20:00:00');
        $this->assertSame(6, $sabado->dayOfWeek, 'Guarda: 2026-08-22 deixou de ser sábado.');

        $this->entrada($fila, $sabado, 20, ClienteFila::STATUS_SAIDA_ATENDIDO, 4);
        $this->entrada($fila, $sabado->copy()->addMinutes(10), 20, ClienteFila::STATUS_SAIDA_ATENDIDO, 2);
        $this->entrada($fila, $this->local('2026-08-19 13:00:00'), 20, ClienteFila::STATUS_SAIDA_ATENDIDO, 3);

        $mapa = $this->analytics->mapaDeCalorDeDemanda($this->restaurante->id, ...$this->agosto());

        $this->assertCount(168, $mapa['celulas'], '7 dias x 24 horas, sem buraco.');

        $celulas = collect($mapa['celulas'])->keyBy(fn ($c) => $c['dia_da_semana'].':'.$c['hora']);

        $this->assertSame(2, $celulas['6:20']['entradas']);
        $this->assertSame(6, $celulas['6:20']['pessoas']);
        $this->assertSame(1, $celulas['3:13']['entradas']);
        $this->assertSame(0, $celulas['1:5']['entradas']);

        $this->assertSame(6, $mapa['pico']['dia_da_semana']);
        $this->assertSame(20, $mapa['pico']['hora']);
        $this->assertSame('sábado', $mapa['pico']['nome']);
    }

    public function test_procedencia_separa_dado_real_de_sintetico(): void
    {
        $fila = Fila::factory()->for($this->restaurante)->create();

        $real = $this->entrada($fila, $this->local('2026-08-19 13:00:00'), 20, ClienteFila::STATUS_SAIDA_ATENDIDO);
        $sintetico = $this->entrada($fila, $this->local('2026-08-19 14:00:00'), 20, ClienteFila::STATUS_SAIDA_ATENDIDO);

        // 'origem' fica fora do $fillable — o seeder também a carimba direto.
        DB::table('clientefila')->where('id', $sintetico->id)->update(['origem' => 'sintetico']);

        $procedencia = $this->analytics->procedenciaDoHistorico($this->restaurante->id, ...$this->agosto());

        $this->assertSame(1, $procedencia['fila']['real']);
        $this->assertSame(1, $procedencia['fila']['sintetico']);
        $this->assertTrue($procedencia['contem_sintetico']);
        $this->assertNotNull($real->id);
    }

    // ─── B) Fuso horário ─────────────────────────────────────

    public function test_entrada_no_fim_do_dia_utc_e_agrupada_no_dia_local_correto(): void
    {
        $fila = Fila::factory()->for($this->restaurante)->create();

        // 23:30 UTC de quarta = 20:30 de quarta em São Paulo.
        $this->entrada($fila, Carbon::parse('2026-08-19 23:30:00', 'UTC'), 15, ClienteFila::STATUS_SAIDA_ATENDIDO);

        $porDia = collect($this->analytics->tempoMedioDeEsperaPorDiaDaSemana(
            $this->restaurante->id,
            ...$this->agosto()
        ))->keyBy('dia_da_semana');

        $porHora = collect($this->analytics->tempoMedioDeEsperaPorFaixaHoraria(
            $this->restaurante->id,
            ...$this->agosto()
        ))->keyBy('hora');

        $this->assertSame(1, $porDia[3]['entradas'], 'Deveria cair na quarta-feira local.');
        $this->assertSame(1, $porHora[20]['entradas'], 'Sem AT TIME ZONE isto cairia na hora 23.');
        $this->assertSame(0, $porHora[23]['entradas']);
    }

    public function test_entrada_de_madrugada_utc_pertence_ao_dia_anterior_no_fuso_local(): void
    {
        $fila = Fila::factory()->for($this->restaurante)->create();

        // 02:00 UTC de quinta = 23:00 de QUARTA em São Paulo — dia anterior.
        $this->entrada($fila, Carbon::parse('2026-08-20 02:00:00', 'UTC'), 15, ClienteFila::STATUS_SAIDA_ATENDIDO);

        $porDia = collect($this->analytics->tempoMedioDeEsperaPorDiaDaSemana(
            $this->restaurante->id,
            ...$this->agosto()
        ))->keyBy('dia_da_semana');

        $porHora = collect($this->analytics->tempoMedioDeEsperaPorFaixaHoraria(
            $this->restaurante->id,
            ...$this->agosto()
        ))->keyBy('hora');

        $this->assertSame(1, $porDia[3]['entradas'], 'Sem conversão de fuso isto cairia na quinta (4).');
        $this->assertSame(0, $porDia[4]['entradas']);
        $this->assertSame(1, $porHora[23]['entradas']);
        $this->assertSame(0, $porHora[2]['entradas']);
    }

    // ─── C) Isolamento entre restaurantes ────────────────────

    public function test_agregados_nao_vazam_dado_de_outro_restaurante(): void
    {
        $outro = Restaurante::factory()->create([
            'horario_abertura' => '11:00',
            'horario_fechamento' => '23:00',
        ]);

        $minhaFila = Fila::factory()->for($this->restaurante)->create();
        $filaAlheia = Fila::factory()->for($outro)->create();

        $this->entrada($minhaFila, $this->local('2026-08-19 13:00:00'), 10, ClienteFila::STATUS_SAIDA_ATENDIDO);

        $this->entrada($filaAlheia, $this->local('2026-08-19 13:00:00'), 90, ClienteFila::STATUS_SAIDA_DESISTIU);
        $this->entrada($filaAlheia, $this->local('2026-08-19 14:00:00'), 90, ClienteFila::STATUS_SAIDA_DESISTIU);

        $minhaMesa = Mesa::factory()->for($this->restaurante)->create(['numero' => 1]);
        $mesaAlheia = Mesa::factory()->for($outro)->create(['numero' => 1]);

        $this->reservaLiberada($minhaMesa, $this->local('2026-08-19 19:00:00'), 60);
        $this->reservaLiberada($mesaAlheia, $this->local('2026-08-19 19:00:00'), 240);

        $abandono = $this->analytics->taxaDeAbandono($this->restaurante->id, ...$this->agosto());
        $this->assertSame(1, $abandono['total_entradas']);
        $this->assertSame(0, $abandono['desistiu']);

        $giro = $this->analytics->giroDeMesa($this->restaurante->id, ...$this->agosto());
        $this->assertCount(1, $giro['mesas'], 'Só as mesas do próprio restaurante.');
        $this->assertSame(3600.0, $giro['duracao_media_geral_segundos'], 'A permanência de 240min é do outro restaurante.');

        $mapa = $this->analytics->mapaDeCalorDeDemanda($this->restaurante->id, ...$this->agosto());
        $this->assertSame(1, collect($mapa['celulas'])->sum('entradas'));
    }

    // ─── D) Período sem dado ─────────────────────────────────

    public function test_periodo_sem_dado_devolve_estrutura_completa_sem_divisao_por_zero(): void
    {
        Mesa::factory()->for($this->restaurante)->create(['numero' => 1]);

        [$inicio, $fim] = $this->agosto();

        $this->assertCount(7, $this->analytics->tempoMedioDeEsperaPorDiaDaSemana($this->restaurante->id, $inicio, $fim));
        $this->assertCount(24, $this->analytics->tempoMedioDeEsperaPorFaixaHoraria($this->restaurante->id, $inicio, $fim));
        $this->assertCount(31, $this->analytics->taxaDeOcupacao($this->restaurante->id, $inicio, $fim));

        $abandono = $this->analytics->taxaDeAbandono($this->restaurante->id, $inicio, $fim);
        $this->assertSame(0, $abandono['total_entradas']);
        $this->assertSame(0.0, $abandono['taxa']);

        $giro = $this->analytics->giroDeMesa($this->restaurante->id, $inicio, $fim);
        $this->assertSame(0, $giro['total_atendimentos']);
        $this->assertSame(0.0, $giro['duracao_media_geral_segundos']);
        $this->assertSame(0.0, $giro['giro_medio_por_mesa']);

        $mapa = $this->analytics->mapaDeCalorDeDemanda($this->restaurante->id, $inicio, $fim);
        $this->assertCount(168, $mapa['celulas']);
        $this->assertNull($mapa['pico']);

        $procedencia = $this->analytics->procedenciaDoHistorico($this->restaurante->id, $inicio, $fim);
        $this->assertFalse($procedencia['contem_sintetico']);
    }

    public function test_restaurante_sem_mesa_nao_estoura_a_taxa_de_ocupacao(): void
    {
        $serie = collect($this->analytics->taxaDeOcupacao($this->restaurante->id, ...$this->agosto()));

        $this->assertSame(0, $serie->first()['mesas']);
        $this->assertSame(0, $serie->first()['segundos_disponiveis']);
        $this->assertSame(0.0, $serie->first()['taxa']);
    }

    // ─── E) NULL de no-show ──────────────────────────────────

    public function test_permanencia_nula_de_no_show_nao_entra_na_media(): void
    {
        $mesa = Mesa::factory()->for($this->restaurante)->create(['numero' => 1]);

        $this->reservaLiberada($mesa, $this->local('2026-08-19 19:00:00'), 60);
        $this->reservaExpirada($mesa, $this->local('2026-08-19 21:00:00'));

        $giro = $this->analytics->giroDeMesa($this->restaurante->id, ...$this->agosto());

        // Se o NULL virasse 0, a média cairia para 1800.
        $this->assertSame(3600.0, $giro['duracao_media_geral_segundos']);
        $this->assertSame(1, $giro['total_atendimentos'], 'No-show não é atendimento.');
    }

    // ─── F) Soft delete é o histórico ────────────────────────

    public function test_entradas_encerradas_estao_soft_deleted_e_ainda_assim_sao_agregadas(): void
    {
        $fila = Fila::factory()->for($this->restaurante)->create();

        $entrada = $this->entrada($fila, $this->local('2026-08-19 13:00:00'), 20, ClienteFila::STATUS_SAIDA_ATENDIDO);

        // Pré-condição: registrarSaida() soft-deleta. Se isto deixar de valer,
        // o resto do teste perde o sentido.
        $this->assertNotNull(
            DB::table('clientefila')->where('id', $entrada->id)->value('deleted_at'),
            'registrarSaida() deveria ter feito soft delete.'
        );
        $this->assertSame(0, ClienteFila::query()->count(), 'Eloquent sem withTrashed() não enxerga histórico.');

        $abandono = $this->analytics->taxaDeAbandono($this->restaurante->id, ...$this->agosto());

        $this->assertSame(1, $abandono['total_entradas'], 'O agregado precisa incluir os soft-deleted.');
    }

    // ─── G) Integração com o seeder de histórico ─────────────

    /**
     * Fecha o ciclo entre quem escreve e quem lê.
     *
     * O HistoricoSinteticoCurvaTest já garante que a CURVA gerada tem dois picos
     * e que sábado é o dia mais cheio — mas ele testa a matemática do seeder, em
     * memória, sem passar pelo banco. Este teste verifica que o agregado
     * consegue ENXERGAR essas curvas depois de ida e volta ao Postgres.
     *
     * É o teste que pegaria uma quebra de fuso que passasse pelos demais: se a
     * conversão saísse errada, os picos de almoço e jantar apareceriam
     * deslocados três horas e a asserção de madrugada cairia.
     */
    public function test_agregado_enxerga_as_curvas_que_o_seeder_gravou(): void
    {
        Mesa::factory()->count(8)->for($this->restaurante)->create();

        app(HistoricoSinteticoSeeder::class)->gerar($this->restaurante, 3);

        $fim = Carbon::now(self::FUSO);
        $inicio = $fim->copy()->subWeeks(5);

        $porHora = collect($this->analytics->tempoMedioDeEsperaPorFaixaHoraria(
            $this->restaurante->id,
            $inicio,
            $fim
        ))->keyBy('hora');

        $almoco = $porHora[12]['entradas'] + $porHora[13]['entradas'];
        $jantar = $porHora[20]['entradas'] + $porHora[21]['entradas'];
        $madrugada = $porHora[3]['entradas'] + $porHora[4]['entradas'] + $porHora[5]['entradas'];

        $this->assertGreaterThan(0, $almoco, 'O pico de almoço sumiu do agregado.');
        $this->assertGreaterThan(0, $jantar, 'O pico de jantar sumiu do agregado.');
        $this->assertSame(0, $madrugada, 'O restaurante não abre de madrugada — fuso deslocado?');

        $porDia = collect($this->analytics->tempoMedioDeEsperaPorDiaDaSemana(
            $this->restaurante->id,
            $inicio,
            $fim
        ))->keyBy('dia_da_semana');

        $this->assertGreaterThan(
            $porDia[1]['entradas'],
            $porDia[6]['entradas'],
            'Sábado deveria ter mais movimento que segunda, como a curva do seeder define.'
        );

        // O dado do seeder é todo sintético — a procedência tem de dizer isso.
        $procedencia = $this->analytics->procedenciaDoHistorico($this->restaurante->id, $inicio, $fim);
        $this->assertTrue($procedencia['contem_sintetico']);
        $this->assertSame(0, $procedencia['fila']['real']);
    }

    // ─── H) Guardas de período ───────────────────────────────

    public function test_periodo_invertido_e_rejeitado(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->analytics->taxaDeAbandono(
            $this->restaurante->id,
            $this->local('2026-08-31 00:00:00'),
            $this->local('2026-08-01 00:00:00')
        );
    }

    public function test_periodo_de_exatamente_366_dias_e_aceito(): void
    {
        // O teto e inclusive. A contagem por float ('365,999... + 1') fazia a
        // guarda rejeitar justamente o valor maximo que deveria permitir.
        //
        // O endOfDay no fim NAO e detalhe: com as duas pontas em 00:00 a conta
        // antiga dava 366 exatos e passava, e este teste nao guardaria nada. E
        // com endOfDay que a diferenca vira 365,999... — e e assim que o
        // AnalyticsController monta o periodo.
        $serie = $this->analytics->taxaDeOcupacao(
            $this->restaurante->id,
            $this->local('2026-01-01 00:00:00'),
            $this->local('2027-01-01 00:00:00')->endOfDay()
        );

        $this->assertCount(366, $serie);
    }

    public function test_periodo_longo_demais_e_rejeitado(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->analytics->taxaDeOcupacao(
            $this->restaurante->id,
            $this->local('2024-01-01 00:00:00'),
            $this->local('2026-01-01 00:00:00')
        );
    }

    // ───────────────────────── Helpers ─────────────────────────

    /** Instante como hora de parede no fuso do restaurante. */
    // ─── E) Desperdício de capacidade (issue #169) ──────────

    public function test_desperdicio_de_capacidade_soma_os_lugares_que_sobraram(): void
    {
        [$inicio, $fim] = $this->agosto();
        $fila = Fila::factory()->for($this->restaurante)->create();

        $mesaDe4 = Mesa::factory()->for($this->restaurante)->comCapacidade(4)->create();
        $mesaDe6 = Mesa::factory()->for($this->restaurante)->comCapacidade(6)->create();

        // 4 lugares para 2 pessoas sobram 2; 6 para 5 sobra 1.
        $this->promovido($fila, $mesaDe4, $this->local('2026-08-19 13:00:00'), 2);
        $this->promovido($fila, $mesaDe6, $this->local('2026-08-20 13:00:00'), 5);

        $metrica = $this->analytics->desperdicioDeCapacidade($this->restaurante->id, $inicio, $fim);

        $this->assertSame(2, $metrica['total_alocacoes']);
        $this->assertSame(10, $metrica['lugares_ofertados']);
        $this->assertSame(7, $metrica['lugares_ocupados']);
        $this->assertSame(3, $metrica['lugares_ociosos']);
        $this->assertSame(1.5, $metrica['desperdicio_medio_por_alocacao']);
        $this->assertEqualsWithDelta(0.3, $metrica['taxa_de_ociosidade'], 0.0001);
    }

    /**
     * A métrica mede a escolha do ALGORITMO. Reserva que o próprio cliente
     * marcou clicando numa mesa não é decisão dele e ficaria como ruído — o que
     * separa as duas é a 'clientemesa_id', que só a promoção preenche.
     */
    public function test_reserva_escolhida_pelo_cliente_nao_entra_no_desperdicio(): void
    {
        [$inicio, $fim] = $this->agosto();
        $fila = Fila::factory()->for($this->restaurante)->create();

        $mesaDe4 = Mesa::factory()->for($this->restaurante)->comCapacidade(4)->create();
        $mesaDe8 = Mesa::factory()->for($this->restaurante)->comCapacidade(8)->create();

        $this->promovido($fila, $mesaDe4, $this->local('2026-08-19 13:00:00'), 2);

        // Reserva avulsa: casal que escolheu sozinho uma mesa de 8. Se entrasse
        // na conta, os 6 lugares ociosos dobrariam o desperdicio medido.
        ClienteMesa::factory()->for($mesaDe8)->create([
            'horario_reserva' => $this->local('2026-08-19 20:00:00')->utc(),
            'party_size' => 2,
            'status' => 'confirmada',
        ]);

        $metrica = $this->analytics->desperdicioDeCapacidade($this->restaurante->id, $inicio, $fim);

        $this->assertSame(1, $metrica['total_alocacoes']);
        $this->assertSame(2, $metrica['lugares_ociosos']);
    }

    public function test_desperdicio_sem_nenhuma_alocacao_nao_divide_por_zero(): void
    {
        [$inicio, $fim] = $this->agosto();

        $metrica = $this->analytics->desperdicioDeCapacidade($this->restaurante->id, $inicio, $fim);

        $this->assertSame(0, $metrica['total_alocacoes']);
        $this->assertSame(0, $metrica['lugares_ociosos']);
        $this->assertSame(0.0, $metrica['desperdicio_medio_por_alocacao']);
        $this->assertSame(0.0, $metrica['taxa_de_ociosidade']);
    }

    /**
     * Entrada de fila promovida para uma mesa, pela mesma porta que o
     * FilaService usa: registrarChamada() é quem preenche 'clientemesa_id', e é
     * essa coluna que a métrica usa para separar a alocação do algoritmo da
     * reserva avulsa. Mesmo cuidado com o relógio do helper entrada().
     */
    private function promovido(Fila $fila, Mesa $mesa, Carbon $chegada, int $pessoas): ClienteFila
    {
        $chegadaUtc = $chegada->copy()->utc();

        Carbon::setTestNow($chegadaUtc->copy()->addMinutes(10));

        try {
            $entrada = ClienteFila::factory()->for($fila)->create([
                'created_at' => $chegadaUtc,
                'qntd_pessoas' => $pessoas,
            ]);

            $reserva = ClienteMesa::factory()->for($mesa)->create([
                'horario_reserva' => $chegadaUtc->copy()->addMinutes(10),
                'party_size' => $pessoas,
                'status' => 'confirmada',
            ]);

            $entrada->registrarChamada($reserva);

            return $entrada;
        } finally {
            Carbon::setTestNow();
        }
    }

    private function local(string $datahora): Carbon
    {
        return Carbon::parse($datahora, self::FUSO);
    }

    /** @return array{0:Carbon, 1:Carbon} agosto/2026 inteiro, em horário local */
    private function agosto(): array
    {
        return [$this->local('2026-08-01 00:00:00'), $this->local('2026-08-31 23:59:59')];
    }

    /**
     * Entrada de fila que chegou em $chegada, esperou $esperaMinutos e saiu.
     *
     * O relógio é congelado no instante da SAÍDA porque registrarSaida() lê
     * now() para gravar 'saiu_em' e calcular 'tempo_espera_segundos'. O
     * 'created_at' explícito no state é o que fixa a chegada — sem ele a
     * factory usaria now()->subMinutes() e a data histórica se perderia.
     *
     * Tudo é convertido para UTC antes de tocar o banco ou o relógio, por dois
     * motivos distintos:
     *
     *   - na gravação, o Eloquent formata o Carbon no fuso que ELE carrega, sem
     *     converter. Passar 13:00 de São Paulo gravaria literalmente '13:00'
     *     numa coluna que o resto do sistema lê como UTC. É a mesma conversão
     *     que o HistoricoSinteticoSeeder faz ao gravar.
     *   - no setTestNow(), o fuso do Carbon é descartado e a hora de parede é
     *     relida como UTC. Congelar o relógio em 13:10 de São Paulo faria now()
     *     devolver 13:10 UTC, e 'tempo_espera_segundos' sairia três horas
     *     deslocado.
     */
    private function entrada(
        Fila $fila,
        Carbon $chegada,
        int $esperaMinutos,
        string $status,
        int $pessoas = 2
    ): ClienteFila {
        $chegadaUtc = $chegada->copy()->utc();

        Carbon::setTestNow($chegadaUtc->copy()->addMinutes($esperaMinutos));

        try {
            $registro = ClienteFila::factory()
                ->for($fila)
                ->create([
                    'created_at' => $chegadaUtc,
                    'qntd_pessoas' => $pessoas,
                ]);

            $registro->registrarSaida($status);

            return $registro;
        } finally {
            Carbon::setTestNow();
        }
    }

    /** Reserva com check-in em $horario e permanência de $ficouMinutos. */
    private function reservaLiberada(Mesa $mesa, Carbon $horario, int $ficouMinutos): ClienteMesa
    {
        $horarioUtc = $horario->copy()->utc();

        Carbon::setTestNow($horarioUtc->copy()->addMinutes($ficouMinutos));

        try {
            $reserva = ClienteMesa::factory()->for($mesa)->create([
                'horario_reserva' => $horarioUtc,
                'horario_checkin' => $horarioUtc,
                'status' => 'liberada',
            ]);

            $reserva->registrarSaida();

            return $reserva;
        } finally {
            Carbon::setTestNow();
        }
    }

    /** No-show: sem check-in, portanto 'duracao_segundos' fica NULL. */
    private function reservaExpirada(Mesa $mesa, Carbon $horario): ClienteMesa
    {
        return ClienteMesa::factory()->for($mesa)->create([
            'horario_reserva' => $horario->copy()->utc(),
            'horario_checkin' => null,
            'status' => 'expirada',
        ]);
    }
}
