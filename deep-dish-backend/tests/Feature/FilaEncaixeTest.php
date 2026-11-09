<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ClienteFila;
use App\Models\ClienteMesa;
use App\Models\Fila;
use App\Models\Mesa;
use App\Models\Restaurante;
use App\Services\FilaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Priorização da fila por encaixe (issue #170).
 *
 * Regra sob teste (config/fila.php): a ordem base é a de chegada; uma mesa que
 * seria muito desperdiçada (mais que X lugares) vai para outro grupo que encaixa
 * nela dentro do limite — exceto para quem já esperou Y minutos, que é protegido.
 * Os testes fixam X=2 e Y=30 explicitamente, para não dependerem do .env.
 */
class FilaEncaixeTest extends TestCase
{
    use RefreshDatabase;

    private Restaurante $restaurante;

    private Fila $fila;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'fila.desperdicio_maximo_lugares' => 2,
            'fila.espera_protegida_minutos' => 30,
        ]);

        $this->restaurante = Restaurante::factory()->comFilaAtiva()->create();
        $this->fila = Fila::factory()->for($this->restaurante)->create();
    }

    // ─── Cenário 1 — melhor encaixe ──────────────────────────

    /**
     * Fila: casal (chegou antes) e grupo de 8. Vaga primeiro uma mesa de 10, e
     * só depois uma de 4. O grupo de 8 senta na de 10; o casal espera e senta na
     * de 4 quando ela vagar — dois grupos atendidos em vez de um.
     *
     * Não há previsão da mesa de 4: a decisão da primeira rodada usa só o que
     * está livre (a mesa de 10) e a regra de desperdício.
     */
    public function test_grupo_que_encaixa_melhor_fica_com_a_mesa_grande_e_o_casal_com_a_pequena(): void
    {
        $casal = $this->entrada(2, 20);
        $grupo = $this->entrada(8, 10);

        Mesa::factory()->for($this->restaurante)->comCapacidade(10)->create();

        $primeira = $this->service()->processarPromocoes($this->restaurante->id);

        $this->assertCount(1, $primeira);
        $this->assertSame(10, $this->capacidadeRecebida($primeira, $grupo));
        $this->assertNull($this->capacidadeRecebida($primeira, $casal));
        $this->assertNull($casal->fresh()->status_saida, 'O casal continua na fila.');

        // Dois minutos depois, a mesa de 4 vaga.
        Mesa::factory()->for($this->restaurante)->comCapacidade(4)->create();

        $segunda = $this->service()->processarPromocoes($this->restaurante->id);

        $this->assertCount(1, $segunda);
        $this->assertSame(4, $this->capacidadeRecebida($segunda, $casal));
    }

    public function test_sem_ninguem_que_encaixe_melhor_o_casal_leva_a_mesa_grande(): void
    {
        $casal = $this->entrada(2, 20);

        Mesa::factory()->for($this->restaurante)->comCapacidade(10)->create();

        $promovidos = $this->service()->processarPromocoes($this->restaurante->id);

        // Mesa parada não ajuda ninguém: sem concorrente melhor, o casal senta.
        $this->assertSame(10, $this->capacidadeRecebida($promovidos, $casal));
    }

    public function test_quem_tambem_desperdicia_demais_nao_justifica_furar_a_fila(): void
    {
        // Os dois sobram muita mesa numa de 10 (8 e 7 lugares ociosos): passar o
        // segundo na frente não melhora a ocupação, então vale a chegada.
        $primeiro = $this->entrada(2, 20);
        $segundo = $this->entrada(3, 10);

        Mesa::factory()->for($this->restaurante)->comCapacidade(10)->create();

        $promovidos = $this->service()->processarPromocoes($this->restaurante->id);

        $this->assertSame(10, $this->capacidadeRecebida($promovidos, $primeiro));
        $this->assertNull($this->capacidadeRecebida($promovidos, $segundo));
    }

    public function test_limite_de_desperdicio_e_configuravel(): void
    {
        // Com X=8, a mesa de 10 para o casal está dentro do limite: vale a chegada.
        config(['fila.desperdicio_maximo_lugares' => 8]);

        $casal = $this->entrada(2, 20);
        $grupo = $this->entrada(8, 10);

        Mesa::factory()->for($this->restaurante)->comCapacidade(10)->create();

        $promovidos = $this->service()->processarPromocoes($this->restaurante->id);

        $this->assertSame(10, $this->capacidadeRecebida($promovidos, $casal));
        $this->assertNull($this->capacidadeRecebida($promovidos, $grupo));
    }

    // ─── Cenário 2 — proteção contra starvation ──────────────

    /**
     * Mesa de 8; o grupo de 6 chegou antes do grupo de 8, que encaixa perfeito.
     * Enquanto o 6 é "jovem" (29 min), o 8 passa na frente. Ao completar Y, a
     * mesa é do 6 — e nenhuma otimização nova o ultrapassa.
     */
    public function test_grupo_jovem_pode_ser_ultrapassado_por_quem_encaixa_melhor(): void
    {
        config(['fila.desperdicio_maximo_lugares' => 1]);

        $seis = $this->entrada(6, 29);
        $oito = $this->entrada(8, 5);
        Mesa::factory()->for($this->restaurante)->comCapacidade(8)->create();

        $antes = $this->service()->processarPromocoes($this->restaurante->id);

        $this->assertSame(8, $this->capacidadeRecebida($antes, $oito), 'Com 29 min o 6 ainda pode ser ultrapassado.');
        $this->assertNull($this->capacidadeRecebida($antes, $seis));
    }

    public function test_grupo_que_atingiu_o_limite_de_espera_nao_sofre_ultrapassagem(): void
    {
        config(['fila.desperdicio_maximo_lugares' => 1]);

        $seis = $this->entrada(6, 30);
        $oito = $this->entrada(8, 5);
        Mesa::factory()->for($this->restaurante)->comCapacidade(8)->create();

        $this->assertTrue($seis->fresh()->protegida);

        $promovidos = $this->service()->processarPromocoes($this->restaurante->id);

        $this->assertSame(8, $this->capacidadeRecebida($promovidos, $seis), 'Protegido recebe a mesa.');
        $this->assertNull($this->capacidadeRecebida($promovidos, $oito));
    }

    /**
     * O cenário do enunciado: um grupo grande espera muito e vão chegando casais
     * que "encaixam melhor" nas mesas pequenas. Cada rodada só tem uma mesa de 6
     * livre — que o grupo protegido de 6 tem de receber, e não um casal.
     */
    public function test_casais_que_chegam_depois_nao_tiram_a_mesa_do_grupo_protegido(): void
    {
        $grande = $this->entrada(6, 45);

        foreach ([15, 10, 5] as $minutos) {
            $this->entrada(2, $minutos);
        }

        Mesa::factory()->for($this->restaurante)->comCapacidade(6)->create();

        $promovidos = $this->service()->processarPromocoes($this->restaurante->id);

        $this->assertCount(1, $promovidos);
        $this->assertSame(6, $this->capacidadeRecebida($promovidos, $grande));
    }

    public function test_protegidos_respeitam_a_ordem_de_chegada_entre_si(): void
    {
        $primeiro = $this->entrada(4, 60);
        $segundo = $this->entrada(4, 40);
        $jovem = $this->entrada(4, 2);

        Mesa::factory()->for($this->restaurante)->comCapacidade(4)->create();

        $promovidos = $this->service()->processarPromocoes($this->restaurante->id);

        $this->assertCount(1, $promovidos);
        $this->assertSame(4, $this->capacidadeRecebida($promovidos, $primeiro));
        $this->assertNull($this->capacidadeRecebida($promovidos, $segundo));
        $this->assertNull($this->capacidadeRecebida($promovidos, $jovem));
    }

    public function test_protegido_sem_mesa_que_o_comporte_nao_trava_os_demais(): void
    {
        // Proteção é prioridade, não garantia de atendimento: sem mesa de 6, o
        // grupo segue esperando e o casal usa a mesa de 2.
        $grande = $this->entrada(6, 60);
        $casal = $this->entrada(2, 5);

        Mesa::factory()->for($this->restaurante)->comCapacidade(2)->create();

        $promovidos = $this->service()->processarPromocoes($this->restaurante->id);

        $this->assertSame(2, $this->capacidadeRecebida($promovidos, $casal));
        $this->assertNull($this->capacidadeRecebida($promovidos, $grande));
        $this->assertNull($grande->fresh()->status_saida);
    }

    public function test_limite_de_espera_protegida_e_configuravel(): void
    {
        config(['fila.desperdicio_maximo_lugares' => 1, 'fila.espera_protegida_minutos' => 5]);

        $seis = $this->entrada(6, 6);
        $oito = $this->entrada(8, 1);
        Mesa::factory()->for($this->restaurante)->comCapacidade(8)->create();

        $promovidos = $this->service()->processarPromocoes($this->restaurante->id);

        $this->assertSame(8, $this->capacidadeRecebida($promovidos, $seis));
    }

    // ─── Cenário 3 — coerência da posição ────────────────────

    public function test_posicao_e_protecao_seguem_a_ordem_usada_pela_chamada(): void
    {
        $a = $this->entrada(2, 30);
        $b = $this->entrada(4, 10);
        $c = $this->entrada(3, 1);

        $this->assertSame(1, $a->fresh()->posicao);
        $this->assertSame(2, $b->fresh()->posicao);
        $this->assertSame(3, $c->fresh()->posicao);

        $this->assertTrue($a->fresh()->protegida);
        $this->assertFalse($b->fresh()->protegida);

        // Quem sai da frente reposiciona quem fica: com 'a' e 'b' chamados, 'c' é o 1º.
        Mesa::factory()->for($this->restaurante)->comCapacidade(4)->create();
        Mesa::factory()->for($this->restaurante)->comCapacidade(3)->create();
        $this->service()->processarPromocoes($this->restaurante->id);

        $this->assertSame(1, $c->fresh()->posicao);
    }

    public function test_endpoint_de_posicao_informa_posicao_e_protecao(): void
    {
        $protegido = $this->entrada(2, 30);
        $jovem = $this->entrada(2, 1);

        $this->consultar($protegido->cliente, $this->fila)
            ->assertOk()
            ->assertJsonPath('posicao', 1)
            ->assertJsonPath('protegida', true);

        $this->consultar($jovem->cliente, $this->fila)
            ->assertOk()
            ->assertJsonPath('posicao', 2)
            ->assertJsonPath('protegida', false);
    }

    public function test_quem_foi_ultrapassado_por_encaixe_segue_sendo_o_primeiro_da_espera(): void
    {
        // A posição é a da fila de espera: o casal foi ultrapassado pelo grupo
        // de 8, mas segue como o 1º de quem ainda aguarda.
        $casal = $this->entrada(2, 20);
        $grupo = $this->entrada(8, 10);

        Mesa::factory()->for($this->restaurante)->comCapacidade(10)->create();
        $this->service()->processarPromocoes($this->restaurante->id);

        $this->assertNotNull(ClienteFila::withTrashed()->findOrFail($grupo->id)->chamado_em);
        $this->assertSame(1, $casal->fresh()->posicao);
    }

    // ─── Cenário 4 — múltiplas filas abertas ─────────────────

    public function test_posicao_e_unica_para_o_restaurante_entre_filas_abertas(): void
    {
        $outraJanela = Fila::factory()->for($this->restaurante)->create([
            'horario_reserva' => now()->utc()->addHours(2)->startOfHour(),
        ]);

        $primeiro = $this->entrada(2, 30);
        $segundo = ClienteFila::factory()->for($outraJanela)->entrouHa(20)->create(['qntd_pessoas' => 2]);
        $terceiro = $this->entrada(2, 10);

        $this->assertSame(1, $primeiro->fresh()->posicao);
        $this->assertSame(2, $segundo->fresh()->posicao, 'Outra janela não reinicia a contagem em 1.');
        $this->assertSame(3, $terceiro->fresh()->posicao);
    }

    public function test_chamada_percorre_as_filas_abertas_na_ordem_de_chegada(): void
    {
        $outraJanela = Fila::factory()->for($this->restaurante)->create([
            'horario_reserva' => now()->utc()->addHours(2)->startOfHour(),
        ]);

        $primeiro = $this->entrada(2, 30);
        $segundo = ClienteFila::factory()->for($outraJanela)->entrouHa(20)->create(['qntd_pessoas' => 2]);

        Mesa::factory()->for($this->restaurante)->comCapacidade(2)->create();

        $promovidos = $this->service()->processarPromocoes($this->restaurante->id);

        $this->assertSame(2, $this->capacidadeRecebida($promovidos, $primeiro));
        $this->assertNull($this->capacidadeRecebida($promovidos, $segundo));
    }

    public function test_painel_do_restaurante_numera_as_filas_abertas_em_sequencia_unica(): void
    {
        $outraJanela = Fila::factory()->for($this->restaurante)->create([
            'horario_reserva' => now()->utc()->addHours(2)->startOfHour(),
        ]);

        $this->entrada(2, 30);
        ClienteFila::factory()->for($outraJanela)->entrouHa(20)->create(['qntd_pessoas' => 2]);
        $this->entrada(2, 10);

        $resposta = $this->withHeader('Authorization', 'Bearer '.auth('restaurante')->login($this->restaurante))
            ->getJson('/api/restaurante/fila')
            ->assertOk();

        $this->assertSame([1, 2, 3], collect($resposta->json())->pluck('posicao')->all());
        $this->assertSame([true, false, false], collect($resposta->json())->pluck('protegida')->all());
    }

    public function test_posicao_estimada_de_quem_ainda_nao_entrou_conta_todas_as_filas_abertas(): void
    {
        $outraJanela = Fila::factory()->for($this->restaurante)->create([
            'horario_reserva' => now()->utc()->addHours(2)->startOfHour(),
        ]);

        $this->entrada(2, 30);
        ClienteFila::factory()->for($outraJanela)->entrouHa(20)->create(['qntd_pessoas' => 2]);

        $this->assertSame(2, $this->service()->contarAtivos($this->restaurante->id));
    }

    // ─── Cenário 5 — regressão da #169 ───────────────────────

    public function test_regressao_169_fila_8_2_5_com_mesas_2_6_10_atende_os_tres(): void
    {
        $oito = $this->entrada(8, 20);
        $dois = $this->entrada(2, 15);
        $cinco = $this->entrada(5, 10);

        foreach ([2, 6, 10] as $capacidade) {
            Mesa::factory()->for($this->restaurante)->comCapacidade($capacidade)->create();
        }

        $promovidos = $this->service()->processarPromocoes($this->restaurante->id);

        $this->assertCount(3, $promovidos);
        $this->assertSame(10, $this->capacidadeRecebida($promovidos, $oito));
        $this->assertSame(2, $this->capacidadeRecebida($promovidos, $dois));
        $this->assertSame(6, $this->capacidadeRecebida($promovidos, $cinco));
    }

    // ─── Cenário 6 — FCFS sem benefício de otimização ────────

    public function test_sem_beneficio_de_encaixe_vale_a_ordem_de_chegada(): void
    {
        // Mesmo tamanho: ninguém encaixa melhor que ninguém.
        $primeiro = $this->entrada(2, 20);
        $segundo = $this->entrada(2, 10);

        Mesa::factory()->for($this->restaurante)->comCapacidade(10)->create();

        $promovidos = $this->service()->processarPromocoes($this->restaurante->id);

        $this->assertCount(1, $promovidos);
        $this->assertSame(10, $this->capacidadeRecebida($promovidos, $primeiro));
        $this->assertNull($this->capacidadeRecebida($promovidos, $segundo));
    }

    public function test_grupo_menor_atras_nao_passa_na_frente(): void
    {
        // O de trás é menor, então encaixa pior: não há o que reordenar.
        $maior = $this->entrada(4, 20);
        $menor = $this->entrada(2, 10);

        Mesa::factory()->for($this->restaurante)->comCapacidade(4)->create();

        $promovidos = $this->service()->processarPromocoes($this->restaurante->id);

        $this->assertSame(4, $this->capacidadeRecebida($promovidos, $maior));
        $this->assertNull($this->capacidadeRecebida($promovidos, $menor));
    }

    // ─── Cenário 7 — concorrência ────────────────────────────

    /**
     * A trava de linha do restaurante (ver FilaPromocaoConcorrenteTest, que
     * encena a disputa com duas conexões) faz a segunda chamada enxergar o
     * resultado da primeira. Aqui: chamadas repetidas não duplicam mesa nem
     * entrada, mesmo com o encaixe redistribuindo quem pega o quê.
     */
    public function test_processamentos_repetidos_nao_duplicam_mesa_nem_entrada(): void
    {
        $this->entrada(2, 20);
        $this->entrada(8, 10);

        Mesa::factory()->for($this->restaurante)->comCapacidade(10)->create();
        Mesa::factory()->for($this->restaurante)->comCapacidade(4)->create();

        $service = $this->service();
        $total = $service->processarPromocoes($this->restaurante->id)->count();
        $total += $service->processarPromocoes($this->restaurante->id)->count();
        $total += $service->processarPromocoes($this->restaurante->id)->count();

        $this->assertSame(2, $total);
        $this->assertSame(2, ClienteMesa::count());
        $this->assertSame(2, ClienteMesa::distinct('mesa_id')->count('mesa_id'));
        $this->assertSame(0, ClienteFila::ativas()->count());
    }

    // ─── Apoio ───────────────────────────────────────────────

    private function service(): FilaService
    {
        return app(FilaService::class);
    }

    private function entrada(int $pessoas, int $haMinutos): ClienteFila
    {
        return ClienteFila::factory()->for($this->fila)->entrouHa($haMinutos)->create(['qntd_pessoas' => $pessoas]);
    }

    private function capacidadeRecebida(Collection $promovidos, ClienteFila $entrada): ?int
    {
        $reserva = $promovidos->firstWhere('cliente_id', $entrada->cliente_id);

        return $reserva === null ? null : (int) Mesa::findOrFail($reserva->mesa_id)->capacidade;
    }

    private function consultar(Cliente $cliente, Fila $fila)
    {
        return $this->withHeader('Authorization', 'Bearer '.auth('api')->login($cliente))
            ->getJson('/api/fila/posicao?'.http_build_query([
                'restaurante_id' => $fila->restaurante_id,
                'horario_reserva' => $fila->horario_reserva->toIso8601String(),
            ]));
    }
}
