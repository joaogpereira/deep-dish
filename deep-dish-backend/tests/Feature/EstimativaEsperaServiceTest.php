<?php

namespace Tests\Feature;

use App\Models\ClienteFila;
use App\Models\Fila;
use App\Models\Mesa;
use App\Models\Restaurante;
use App\Services\EstimativaEsperaService;
use Database\Seeders\HistoricoSinteticoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Cobre a estimativa de espera (#163) e os três níveis de degradação.
 *
 * O histórico é montado com uma relação linear CONHECIDA (espera = base +
 * incremento x posicao) para que os testes verifiquem se o estimador recupera o
 * numero certo — nao apenas se ele roda sem estourar.
 *
 * Cada "fila" do helper e uma semana diferente no mesmo dia e horario: quatro
 * sabados as 20h, por exemplo. Isso exercita o PARTITION BY do ROW_NUMBER (as
 * posicoes reiniciam a cada fila) e evita colisao no indice unico de
 * (restaurante_id, horario_reserva).
 */
class EstimativaEsperaServiceTest extends TestCase
{
    use RefreshDatabase;

    private const FUSO = 'America/Sao_Paulo';

    private EstimativaEsperaService $estimativa;

    private Restaurante $restaurante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->estimativa = app(EstimativaEsperaService::class);
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

    // ─── Nível 1: histórico específico ───────────────────────

    public function test_estimativa_recupera_a_relacao_linear_do_historico(): void
    {
        $slot = $this->slot('2026-08-22 20:00:00'); // sábado

        // 4 semanas x 6 posicoes = 24 observacoes, espera = 10 + 5 x posicao min.
        $this->historicoLinear($slot, filas: 4, porFila: 6, baseMin: 10, porPosicaoMin: 5);

        $r = $this->estimativa->estimar($this->restaurante->id, 5, 2, $slot);

        $this->assertSame(EstimativaEsperaService::NIVEL_ESPECIFICO, $r['nivel']);
        $this->assertSame(24, $r['amostra']);
        // 10 + 5x5 = 35
        $this->assertSame(35, $r['espera_estimada_minutos']);
        $this->assertSame(5, $r['posicao']);
    }

    public function test_estimativa_e_monotonica_na_posicao(): void
    {
        $slot = $this->slot('2026-08-22 20:00:00');
        $this->historicoLinear($slot, filas: 4, porFila: 6, baseMin: 10, porPosicaoMin: 5);

        $anterior = 0;

        foreach (range(1, 10) as $posicao) {
            $atual = $this->estimativa->estimar($this->restaurante->id, $posicao, 2, $slot)['espera_estimada_minutos'];

            $this->assertGreaterThan($anterior, $atual, "Posição {$posicao} deveria esperar mais que a anterior.");
            $anterior = $atual;
        }
    }

    // ─── Nível 2: cai para a média ampla ─────────────────────

    public function test_sem_historico_no_slot_cai_para_o_nivel_amplo(): void
    {
        // Historico existe na sexta as 12h; a consulta pergunta pelo sabado as 20h.
        $this->historicoLinear($this->slot('2026-08-21 12:00:00'), filas: 4, porFila: 6, baseMin: 10, porPosicaoMin: 5);

        $r = $this->estimativa->estimar($this->restaurante->id, 5, 2, $this->slot('2026-08-22 20:00:00'));

        $this->assertSame(EstimativaEsperaService::NIVEL_AMPLO, $r['nivel']);
        $this->assertSame(24, $r['amostra']);
        // A reta e a mesma; so o recorte mudou.
        $this->assertSame(35, $r['espera_estimada_minutos']);
    }

    public function test_grupo_muito_maior_que_o_historico_cai_para_o_amplo(): void
    {
        $slot = $this->slot('2026-08-22 20:00:00');

        // Historico so de grupos de 2 pessoas.
        $this->historicoLinear($slot, filas: 4, porFila: 6, baseMin: 10, porPosicaoMin: 5, pessoas: 2);

        // Grupo de 10 esta fora da tolerancia de +-1, entao o nivel especifico
        // nao tem amostra.
        $r = $this->estimativa->estimar($this->restaurante->id, 5, 10, $slot);

        $this->assertSame(EstimativaEsperaService::NIVEL_AMPLO, $r['nivel']);
    }

    // ─── Nível 3: padrão declarado ───────────────────────────

    public function test_sem_historico_nenhum_devolve_o_padrao(): void
    {
        $r = $this->estimativa->estimar($this->restaurante->id, 5, 2, $this->slot('2026-08-22 20:00:00'));

        $this->assertSame(EstimativaEsperaService::NIVEL_PADRAO, $r['nivel']);
        $this->assertSame(0, $r['amostra']);
        // 5 min fixos + 3 min x 5 posicoes = 20
        $this->assertSame(20, $r['espera_estimada_minutos']);
    }

    public function test_amostra_toda_na_mesma_posicao_nao_qualifica(): void
    {
        $slot = $this->slot('2026-08-22 20:00:00');

        // 25 filas com UMA entrada cada: todas na posicao 1. regr_slope() devolve
        // NULL sem dois valores distintos de x — nao ha reta a tracar.
        $this->historicoLinear($slot, filas: 25, porFila: 1, baseMin: 10, porPosicaoMin: 5);

        $r = $this->estimativa->estimar($this->restaurante->id, 5, 2, $slot);

        $this->assertSame(EstimativaEsperaService::NIVEL_PADRAO, $r['nivel']);
        $this->assertSame(20, $r['espera_estimada_minutos']);
    }

    public function test_amostra_pequena_nao_qualifica(): void
    {
        $slot = $this->slot('2026-08-22 20:00:00');

        // 2 filas x 4 posicoes = 8 observacoes, abaixo do minimo de 20.
        $this->historicoLinear($slot, filas: 2, porFila: 4, baseMin: 10, porPosicaoMin: 5);

        $this->assertSame(
            EstimativaEsperaService::NIVEL_PADRAO,
            $this->estimativa->estimar($this->restaurante->id, 5, 2, $slot)['nivel']
        );
    }

    /**
     * A posição vem da ordem de CHEGADA, não da ordem de insercao no banco.
     *
     * Este teste grava as entradas fora de ordem de proposito. Sem ele a
     * distincao passa despercebida: o Laravel 12 usa UUIDv7, que e ordenavel por
     * tempo de geracao, entao 'ORDER BY id' produz o mesmo resultado que
     * 'ORDER BY created_at' sempre que as linhas nascem em ordem cronologica —
     * como acontece em producao e nos demais testes.
     *
     * Quem grava historico retroativo (o HistoricoSinteticoSeeder, uma
     * importacao) quebra essa coincidencia, e ai a posicao sairia errada.
     */
    public function test_posicao_segue_a_chegada_e_nao_a_ordem_de_insercao(): void
    {
        $slot = $this->slot('2026-08-22 20:00:00');

        foreach (range(0, 3) as $semana) {
            $inicio = $slot->copy()->subWeeks($semana);

            $fila = Fila::factory()->for($this->restaurante)->create([
                'horario_reserva' => $inicio->copy()->utc(),
                'status' => Fila::STATUS_ENCERRADA,
            ]);

            // Insere na ordem 6,5,4,3,2,1 — o inverso da chegada.
            foreach ([6, 5, 4, 3, 2, 1] as $posicao) {
                $this->entrada(
                    $fila,
                    $inicio->copy()->addMinutes($posicao - 1),
                    10 + 5 * $posicao,
                    ClienteFila::STATUS_SAIDA_ATENDIDO,
                    2
                );
            }
        }

        $r = $this->estimativa->estimar($this->restaurante->id, 5, 2, $slot);

        $this->assertSame(EstimativaEsperaService::NIVEL_ESPECIFICO, $r['nivel']);
        // Mesma reta do teste em ordem: 10 + 5x5 = 35.
        $this->assertSame(35, $r['espera_estimada_minutos']);
    }

    /**
     * Regressão da #170: a posição da amostra é a do RESTAURANTE (todas as filas
     * abertas), a mesma que a tela passa a estimar — e não a do fila_id.
     *
     * Em cada semana há duas janelas de horário. A (1ª entrada) e B (na outra
     * janela, 1 min depois, com A ainda esperando) formam as posições 1 e 2; C chega
     * depois que os dois já saíram e volta a ser a posição 1. Contando por fila_id
     * todos seriam "posição 1" e o estimador cairia no padrão fixo; contando sem
     * descontar quem já saiu, C seria a posição 3 e a reta sairia torta.
     */
    public function test_posicao_do_historico_conta_as_filas_do_restaurante_e_desconta_quem_ja_saiu(): void
    {
        $slot = $this->slot('2026-08-22 20:00:00');

        foreach (range(0, 11) as $semana) {
            $inicio = $slot->copy()->subWeeks($semana);

            $janelaA = Fila::factory()->for($this->restaurante)->create([
                'horario_reserva' => $inicio->copy()->utc(),
                'status' => Fila::STATUS_ENCERRADA,
            ]);
            $janelaB = Fila::factory()->for($this->restaurante)->create([
                'horario_reserva' => $inicio->copy()->addHour()->utc(),
                'status' => Fila::STATUS_ENCERRADA,
            ]);

            // espera = 10 + 5 x posicao (min)
            $this->entrada($janelaA, $inicio, 15, ClienteFila::STATUS_SAIDA_ATENDIDO, 2);                       // pos 1, sai em +15
            $this->entrada($janelaB, $inicio->copy()->addMinute(), 20, ClienteFila::STATUS_SAIDA_ATENDIDO, 2);  // pos 2, sai em +21
            $this->entrada($janelaA, $inicio->copy()->addMinutes(30), 15, ClienteFila::STATUS_SAIDA_ATENDIDO, 2); // pos 1 de novo
        }

        $r = $this->estimativa->estimar($this->restaurante->id, 3, 2, $slot);

        $this->assertSame(EstimativaEsperaService::NIVEL_ESPECIFICO, $r['nivel']);
        $this->assertSame(36, $r['amostra']);
        // 10 + 5 x 3
        $this->assertSame(25, $r['espera_estimada_minutos']);
    }

    // ─── Qualidade da amostra ────────────────────────────────

    public function test_desistentes_nao_puxam_a_estimativa_para_baixo(): void
    {
        $slot = $this->slot('2026-08-22 20:00:00');

        $this->historicoLinear($slot, filas: 4, porFila: 6, baseMin: 10, porPosicaoMin: 5);
        $semDesistentes = $this->estimativa->estimar($this->restaurante->id, 5, 2, $slot);

        // Uma enxurrada de desistencias rapidas: dado censurado, fora da amostra.
        $this->historicoLinear(
            $slot->copy()->subWeeks(10),
            filas: 8,
            porFila: 6,
            baseMin: 1,
            porPosicaoMin: 0,
            pessoas: 2,
            status: ClienteFila::STATUS_SAIDA_DESISTIU
        );

        $comDesistentes = $this->estimativa->estimar($this->restaurante->id, 5, 2, $slot);

        $this->assertSame(
            $semDesistentes['espera_estimada_minutos'],
            $comDesistentes['espera_estimada_minutos'],
            'Quem desistiu nao terminou de esperar — nao pode entrar na conta.'
        );
        $this->assertSame(24, $comDesistentes['amostra']);
    }

    public function test_historico_de_outro_restaurante_nao_influencia(): void
    {
        $slot = $this->slot('2026-08-22 20:00:00');

        $vizinho = Restaurante::factory()->create();
        $this->historicoLinear($slot, filas: 8, porFila: 6, baseMin: 90, porPosicaoMin: 20, restaurante: $vizinho);

        $r = $this->estimativa->estimar($this->restaurante->id, 5, 2, $slot);

        $this->assertSame(EstimativaEsperaService::NIVEL_PADRAO, $r['nivel']);
        $this->assertSame(20, $r['espera_estimada_minutos']);
    }

    // ─── Robustez ────────────────────────────────────────────

    public function test_nunca_devolve_estimativa_nao_positiva(): void
    {
        $slot = $this->slot('2026-08-22 20:00:00');

        foreach ([-5, 0, 1, 999] as $posicao) {
            $r = $this->estimativa->estimar($this->restaurante->id, $posicao, 2, $slot);

            $this->assertGreaterThan(0, $r['espera_estimada_minutos']);
            $this->assertGreaterThanOrEqual(1, $r['posicao'], 'Posição é normalizada para no mínimo 1.');
        }
    }

    public function test_restaurante_inexistente_nao_lanca_e_cai_no_padrao(): void
    {
        $r = $this->estimativa->estimar('0199e3d0-0000-7000-8000-000000000000', 3, 2);

        $this->assertSame(EstimativaEsperaService::NIVEL_PADRAO, $r['nivel']);
        $this->assertSame(14, $r['espera_estimada_minutos']); // 5 + 3x3
    }

    // ─── Fecha o ciclo com o seeder ──────────────────────────

    /**
     * O HistoricoSinteticoSeeder gera 'esperaBase = 5 + 3,2 x posicao' minutos,
     * com fator aleatorio de 0,8 a 1,25 para quem e atendido. Se o estimador
     * estiver correto, o incremento por posicao que ele recupera tem de ficar
     * na vizinhanca desses 3,2 min.
     *
     * E a diferenca entre testar que o estimador RODA e testar que ele ACERTA.
     */
    public function test_estimador_recupera_o_incremento_por_posicao_do_seeder(): void
    {
        Mesa::factory()->count(8)->for($this->restaurante)->create();

        app(HistoricoSinteticoSeeder::class)->gerar($this->restaurante, 3);

        $uma = $this->estimativa->estimar($this->restaurante->id, 1, 2);
        $onze = $this->estimativa->estimar($this->restaurante->id, 11, 2);

        $this->assertNotSame(
            EstimativaEsperaService::NIVEL_PADRAO,
            $onze['nivel'],
            'Com 3 semanas de historico deveria haver amostra suficiente.'
        );

        $incremento = ($onze['espera_estimada_minutos'] - $uma['espera_estimada_minutos']) / 10;

        $this->assertGreaterThan(1.5, $incremento, "Incremento de {$incremento} min/posicao e baixo demais.");
        $this->assertLessThan(6.0, $incremento, "Incremento de {$incremento} min/posicao e alto demais.");
    }

    // ───────────────────────── Helpers ─────────────────────────

    private function slot(string $datahora): Carbon
    {
        return Carbon::parse($datahora, self::FUSO);
    }

    /**
     * Monta histórico com relação linear conhecida: espera = base + inc x posicao.
     *
     * Cada fila e uma semana anterior no MESMO dia da semana e horario, para as
     * observacoes cairem todas no mesmo recorte do nivel especifico.
     */
    private function historicoLinear(
        Carbon $slot,
        int $filas,
        int $porFila,
        int $baseMin,
        int $porPosicaoMin,
        int $pessoas = 2,
        string $status = ClienteFila::STATUS_SAIDA_ATENDIDO,
        ?Restaurante $restaurante = null
    ): void {
        $restaurante ??= $this->restaurante;

        foreach (range(0, $filas - 1) as $semana) {
            $inicio = $slot->copy()->subWeeks($semana);

            $fila = Fila::factory()->for($restaurante)->create([
                'horario_reserva' => $inicio->copy()->utc(),
                'status' => Fila::STATUS_ENCERRADA,
            ]);

            foreach (range(1, $porFila) as $posicao) {
                // +1 min por posicao mantem a ordem de chegada (e portanto o
                // ROW_NUMBER) sem sair da faixa horaria do slot.
                $chegada = $inicio->copy()->addMinutes($posicao - 1);
                $espera = $baseMin + $porPosicaoMin * $posicao;

                $this->entrada($fila, $chegada, $espera, $status, $pessoas);
            }
        }
    }

    /**
     * Uma entrada histórica com chegada e espera controladas.
     *
     * Tudo em UTC antes de gravar e antes do setTestNow(): o Eloquent formata o
     * Carbon no fuso que ele carrega, e o setTestNow descarta o fuso ao congelar
     * o relogio. Foi onde os dois bugs do #161 apareceram.
     */
    private function entrada(Fila $fila, Carbon $chegada, int $esperaMinutos, string $status, int $pessoas): void
    {
        $chegadaUtc = $chegada->copy()->utc();

        Carbon::setTestNow($chegadaUtc->copy()->addMinutes($esperaMinutos));

        try {
            ClienteFila::factory()
                ->for($fila)
                ->create([
                    'created_at' => $chegadaUtc,
                    'qntd_pessoas' => $pessoas,
                ])
                ->registrarSaida($status);
        } finally {
            Carbon::setTestNow();
        }
    }
}
