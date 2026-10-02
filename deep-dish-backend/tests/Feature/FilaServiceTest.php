<?php

namespace Tests\Feature;

use App\Http\Controllers\FilaController;
use App\Models\Cliente;
use App\Models\ClienteFila;
use App\Models\Fila;
use App\Models\Mesa;
use App\Models\Restaurante;
use App\Services\FilaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Cobre os três caminhos de saída da fila (cancelamento pelo cliente, promoção
 * para mesa, remoção pelo restaurante — este último vive em FilaController,
 * não em FilaService; ver comentário em FilaService::encerrarFilaSeVazia()),
 * o encerramento de fila vazia, a estabilidade de posição de quem fica e a
 * reentrada, e a checagem de posse na remoção pelo restaurante (issue #155).
 *
 * RefreshDatabase roda contra o host resolvido pelo phpunit.xml — a trava em
 * tests/TestCase.php (camadas 1 e 2) já garante que isso nunca é produção.
 */
class FilaServiceTest extends TestCase
{
    use RefreshDatabase;

    // ─── A) Os três caminhos de saída ────────────────────────

    public function test_cancelamento_pelo_cliente_grava_status_saida_e_dados_da_saida(): void
    {
        $cliente = Cliente::factory()->create();
        $fila = Fila::factory()->create();
        $entrada = ClienteFila::factory()->for($fila)->for($cliente)->create([
            'created_at' => now()->subMinutes(5),
        ]);

        $ok = app(FilaService::class)->cancelarPosicao($entrada->id, $cliente->id);

        $this->assertTrue($ok);

        // Soft delete já rodou dentro de registrarSaida() — sem withTrashed()
        // o registro "some" da query e o teste passaria mesmo se os campos
        // de saída não tivessem sido gravados.
        $saida = ClienteFila::withTrashed()->findOrFail($entrada->id);

        $this->assertSame(ClienteFila::STATUS_SAIDA_DESISTIU, $saida->status_saida);
        $this->assertNotNull($saida->saiu_em);
        $this->assertNotNull($saida->tempo_espera_segundos);
        $this->assertGreaterThanOrEqual(0, $saida->tempo_espera_segundos);
    }

    public function test_promocao_para_mesa_grava_status_saida_e_dados_da_saida(): void
    {
        $restaurante = Restaurante::factory()->comFilaAtiva()->create();
        $fila = Fila::factory()->for($restaurante)->create();
        $entrada = ClienteFila::factory()->for($fila)->create([
            'qntd_pessoas' => 2,
            'created_at' => now()->subMinutes(10),
        ]);
        Mesa::factory()->for($restaurante)->create(['capacidade' => 4]);

        $clienteMesa = app(FilaService::class)->processarPromocoes($restaurante->id)->first();

        $this->assertNotNull($clienteMesa, 'processarPromocoes() não chamou ninguém — checar capacidade/qntd_pessoas.');

        $saida = ClienteFila::withTrashed()->findOrFail($entrada->id);

        $this->assertSame(ClienteFila::STATUS_SAIDA_ATENDIDO, $saida->status_saida);
        $this->assertNotNull($saida->saiu_em);
        $this->assertNotNull($saida->tempo_espera_segundos);
        $this->assertGreaterThanOrEqual(0, $saida->tempo_espera_segundos);
    }

    public function test_remocao_pelo_restaurante_grava_status_saida_e_dados_da_saida(): void
    {
        $restaurante = Restaurante::factory()->create();
        $fila = Fila::factory()->for($restaurante)->create();
        $entrada = ClienteFila::factory()->for($fila)->create([
            'created_at' => now()->subMinutes(3),
        ]);

        $this->actingAs($restaurante, 'restaurante');

        $response = app(FilaController::class)->removerRestaurante($entrada->id);

        $this->assertSame(200, $response->getStatusCode());

        $saida = ClienteFila::withTrashed()->findOrFail($entrada->id);

        $this->assertSame(ClienteFila::STATUS_SAIDA_REMOVIDO, $saida->status_saida);
        $this->assertNotNull($saida->saiu_em);
        $this->assertNotNull($saida->tempo_espera_segundos);
        $this->assertGreaterThanOrEqual(0, $saida->tempo_espera_segundos);
    }

    // ─── B) Encerramento de fila ─────────────────────────────

    public function test_fila_que_fica_vazia_apos_saida_e_encerrada(): void
    {
        $cliente = Cliente::factory()->create();
        $fila = Fila::factory()->create();
        $entrada = ClienteFila::factory()->for($fila)->for($cliente)->create();

        app(FilaService::class)->cancelarPosicao($entrada->id, $cliente->id);

        $this->assertSame(Fila::STATUS_ENCERRADA, $fila->fresh()->status);
    }

    public function test_fila_que_ainda_tem_gente_nao_e_encerrada_apos_uma_saida(): void
    {
        $cliente = Cliente::factory()->create();
        $fila = Fila::factory()->create();
        $entradaQueSai = ClienteFila::factory()->for($fila)->for($cliente)->create();
        ClienteFila::factory()->for($fila)->create(); // continua ativo na fila

        app(FilaService::class)->cancelarPosicao($entradaQueSai->id, $cliente->id);

        $this->assertSame(Fila::STATUS_ABERTA, $fila->fresh()->status);
    }

    // ─── C) Posição e reentrada ──────────────────────────────

    public function test_saida_do_meio_da_fila_nao_altera_posicao_relativa_dos_que_ficaram(): void
    {
        $fila = Fila::factory()->create();

        $primeiro = ClienteFila::factory()->for($fila)->create(['created_at' => now()->subMinutes(3)]);
        $doMeio = ClienteFila::factory()->for($fila)->create(['created_at' => now()->subMinutes(2)]);
        $ultimo = ClienteFila::factory()->for($fila)->create(['created_at' => now()->subMinute()]);

        app(FilaService::class)->cancelarPosicao($doMeio->id, $doMeio->cliente_id);

        $this->assertSame(1, $primeiro->fresh()->posicao);
        $this->assertSame(2, $ultimo->fresh()->posicao);
    }

    public function test_cliente_que_saiu_pode_reentrar_na_fila(): void
    {
        // comFilaAtiva: entrar na fila exige a flag ligada (#168).
        $restaurante = Restaurante::factory()->comFilaAtiva()->create();
        // Salão em operação: fila sem nenhuma mesa fora de bloqueio é recusada.
        Mesa::factory()->for($restaurante)->create(['capacidade' => 4, 'status' => 'ocupada']);
        $cliente = Cliente::factory()->create();
        $horario = now()->addHour()->utc()->format('Y-m-d H:i:s');

        $filaService = app(FilaService::class);

        $primeiraEntrada = $filaService->enfileirar($cliente->id, $restaurante->id, $horario, 2);
        $filaService->cancelarPosicao($primeiraEntrada->id, $cliente->id);

        $segundaEntrada = $filaService->enfileirar($cliente->id, $restaurante->id, $horario, 2);

        $this->assertNotSame($primeiraEntrada->id, $segundaEntrada->id);
        $this->assertNull($segundaEntrada->status_saida);

        $ativa = ClienteFila::ativas()->where('cliente_id', $cliente->id)->get();
        $this->assertCount(1, $ativa, 'Cliente deveria ter exatamente uma entrada ativa após reentrar.');
    }

    // ─── D) Segurança — posse na remoção pelo restaurante (issue #155) ──

    public function test_restaurante_remove_entrada_da_propria_fila(): void
    {
        $restaurante = Restaurante::factory()->create();
        $fila = Fila::factory()->for($restaurante)->create();
        $entrada = ClienteFila::factory()->for($fila)->create();

        $this->actingAs($restaurante, 'restaurante');

        $response = app(FilaController::class)->removerRestaurante($entrada->id);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            ClienteFila::STATUS_SAIDA_REMOVIDO,
            ClienteFila::withTrashed()->findOrFail($entrada->id)->status_saida
        );
    }

    public function test_restaurante_nao_remove_entrada_de_fila_de_outro_restaurante(): void
    {
        $restauranteDono = Restaurante::factory()->create();
        $restauranteInvasor = Restaurante::factory()->create();

        $fila = Fila::factory()->for($restauranteDono)->create();
        $entrada = ClienteFila::factory()->for($fila)->create();

        $this->actingAs($restauranteInvasor, 'restaurante');

        $response = app(FilaController::class)->removerRestaurante($entrada->id);

        $this->assertSame(404, $response->getStatusCode());
        // Mesma mensagem genérica de "não existe" — não pode vazar que a
        // entrada existe para outro restaurante.
        $this->assertSame('Entrada não encontrada.', $response->getData(true)['message']);

        $intacta = ClienteFila::withTrashed()->findOrFail($entrada->id);
        $this->assertNull($intacta->status_saida);
        $this->assertNull($intacta->saiu_em);
        $this->assertNull($intacta->tempo_espera_segundos);
        $this->assertNull($intacta->deleted_at);
    }

    // ─── E) Alocação de mesa (issue #169) ────────────────────

    /**
     * O bug que a #169 conserta. Antes o laço externo era o das mesas e
     * comparava sempre com o primeiro da fila: a mesa de 4 era testada contra a
     * família de 8, descartada, e ninguém sentava.
     */
    public function test_grupo_grande_na_frente_nao_bloqueia_grupo_pequeno_atras(): void
    {
        $restaurante = Restaurante::factory()->comFilaAtiva()->create();
        $fila = Fila::factory()->for($restaurante)->create();

        $familia = ClienteFila::factory()->for($fila)->entrouHa(20)->create(['qntd_pessoas' => 8]);
        $casal = ClienteFila::factory()->for($fila)->entrouHa(10)->create(['qntd_pessoas' => 2]);

        Mesa::factory()->for($restaurante)->comCapacidade(4)->create();

        $promovidos = app(FilaService::class)->processarPromocoes($restaurante->id);

        $this->assertCount(1, $promovidos);
        $this->assertSame(4, $this->capacidadeRecebida($promovidos, $casal));
        $this->assertNull($this->capacidadeRecebida($promovidos, $familia));

        // A família não cabe em lugar nenhum, mas continua esperando.
        $this->assertNull(ClienteFila::withTrashed()->findOrFail($familia->id)->status_saida);
    }

    /**
     * O segundo modo de falha, mais sutil: a mesa pequena era testada contra a
     * família, descartada, e não voltava ao laço para o casal que vinha atrás.
     */
    public function test_mesa_descartada_no_inicio_ainda_serve_para_quem_esta_atras(): void
    {
        $restaurante = Restaurante::factory()->comFilaAtiva()->create();
        $fila = Fila::factory()->for($restaurante)->create();

        $familia = ClienteFila::factory()->for($fila)->entrouHa(20)->create(['qntd_pessoas' => 8]);
        $casal = ClienteFila::factory()->for($fila)->entrouHa(10)->create(['qntd_pessoas' => 2]);

        Mesa::factory()->for($restaurante)->comCapacidade(2)->create();
        Mesa::factory()->for($restaurante)->comCapacidade(10)->create();

        $promovidos = app(FilaService::class)->processarPromocoes($restaurante->id);

        $this->assertCount(2, $promovidos);
        $this->assertSame(10, $this->capacidadeRecebida($promovidos, $familia));
        $this->assertSame(2, $this->capacidadeRecebida($promovidos, $casal));
    }

    /** Best-fit: entre duas que cabem, vai para a que desperdiça menos lugar. */
    public function test_escolhe_a_menor_mesa_que_cabe(): void
    {
        $restaurante = Restaurante::factory()->comFilaAtiva()->create();
        $fila = Fila::factory()->for($restaurante)->create();

        $casal = ClienteFila::factory()->for($fila)->entrouHa(10)->create(['qntd_pessoas' => 2]);

        Mesa::factory()->for($restaurante)->comCapacidade(10)->create();
        Mesa::factory()->for($restaurante)->comCapacidade(2)->create();

        $promovidos = app(FilaService::class)->processarPromocoes($restaurante->id);

        $this->assertSame(2, $this->capacidadeRecebida($promovidos, $casal));
    }

    /**
     * Guarda contra a #169 virar a #170 sem querer: quando os dois cabem na
     * única mesa, quem chegou primeiro senta. Reordenar a fila por encaixe é
     * escopo da #170, que traz a regra de anti-starvation junto.
     */
    public function test_ordem_de_chegada_e_respeitada_quando_os_dois_cabem(): void
    {
        $restaurante = Restaurante::factory()->comFilaAtiva()->create();
        $fila = Fila::factory()->for($restaurante)->create();

        $primeiro = ClienteFila::factory()->for($fila)->entrouHa(20)->create(['qntd_pessoas' => 2]);
        $segundo = ClienteFila::factory()->for($fila)->entrouHa(10)->create(['qntd_pessoas' => 2]);

        Mesa::factory()->for($restaurante)->comCapacidade(4)->create();

        $promovidos = app(FilaService::class)->processarPromocoes($restaurante->id);

        $this->assertCount(1, $promovidos);
        $this->assertSame(4, $this->capacidadeRecebida($promovidos, $primeiro));
        $this->assertNull($this->capacidadeRecebida($promovidos, $segundo));
    }

    /**
     * Limitação conhecida e deferida: grupo maior que qualquer mesa do salão só
     * é atendido juntando mesas, que o sistema ainda não faz. O que este teste
     * garante é que ele não trava a fila para quem vem atrás.
     */
    public function test_grupo_maior_que_qualquer_mesa_nao_trava_a_fila(): void
    {
        $restaurante = Restaurante::factory()->comFilaAtiva()->create();
        $fila = Fila::factory()->for($restaurante)->create();

        $excursao = ClienteFila::factory()->for($fila)->entrouHa(20)->create(['qntd_pessoas' => 12]);
        $casal = ClienteFila::factory()->for($fila)->entrouHa(10)->create(['qntd_pessoas' => 2]);

        Mesa::factory()->for($restaurante)->comCapacidade(4)->create();

        $promovidos = app(FilaService::class)->processarPromocoes($restaurante->id);

        $this->assertCount(1, $promovidos);
        $this->assertSame(4, $this->capacidadeRecebida($promovidos, $casal));
        $this->assertNull(ClienteFila::withTrashed()->findOrFail($excursao->id)->status_saida);
    }

    /**
     * Critério de aceite da #169: a alocação nova desperdiça menos que a greedy
     * antiga no mesmo cenário.
     *
     * A greedy está reproduzida aqui como referência porque saiu do código — é
     * a baseline da comparação, e sem ela o "melhorou" não tem número.
     *
     * Atenção ao que se compara: o TOTAL de lugares ociosos sobe (2 -> 3), e isso
     * é consequência de sentar mais gente, não piora. O número honesto é o
     * desperdício POR ALOCAÇÃO, que é o que a métrica do AnalyticsService expõe.
     */
    public function test_alocacao_nova_desperdica_menos_por_mesa_que_a_greedy_antiga(): void
    {
        $capacidades = [2, 6, 10];
        $chegadas = [[8, 20], [2, 15], [5, 10]];

        // ── baseline: o algoritmo antigo, no mesmo cenário ──
        $antiga = $this->greedyAntiga(array_column($chegadas, 0), $capacidades);

        $this->assertCount(1, $antiga, 'A greedy antiga deveria sentar só o primeiro da fila.');
        $desperdicioAntigo = $this->desperdicioMedio($antiga);

        // ── o algoritmo atual, pelo serviço de verdade ──
        $restaurante = Restaurante::factory()->comFilaAtiva()->create();
        $fila = Fila::factory()->for($restaurante)->create();

        foreach ($capacidades as $capacidade) {
            Mesa::factory()->for($restaurante)->comCapacidade($capacidade)->create();
        }

        foreach ($chegadas as [$pessoas, $ha]) {
            ClienteFila::factory()->for($fila)->entrouHa($ha)->create(['qntd_pessoas' => $pessoas]);
        }

        $promovidos = app(FilaService::class)->processarPromocoes($restaurante->id);

        $nova = $promovidos
            ->map(fn ($reserva) => [(int) $reserva->party_size, (int) Mesa::findOrFail($reserva->mesa_id)->capacidade])
            ->all();

        $desperdicioNovo = $this->desperdicioMedio($nova);

        // Senta mais gente...
        $this->assertCount(3, $nova);
        $this->assertGreaterThan(count($antiga), count($nova));

        // ...e ainda assim encaixa melhor: 2,0 lugares ociosos por mesa contra 1,0.
        $this->assertSame(2.0, $desperdicioAntigo);
        $this->assertSame(1.0, $desperdicioNovo);
        $this->assertLessThan($desperdicioAntigo, $desperdicioNovo);
    }

    /**
     * A regra antiga, reproduzida: laço externo nas MESAS, sempre comparando com
     * o primeiro da fila — quem não cabia parava tudo.
     *
     * @param  list<int>  $tamanhos  grupos na ordem de chegada
     * @param  list<int>  $capacidades
     * @return list<array{0: int, 1: int}>
     */
    private function greedyAntiga(array $tamanhos, array $capacidades): array
    {
        sort($capacidades);   // mesasDisponiveis() ordena por capacidade crescente
        $alocacoes = [];

        foreach ($capacidades as $capacidade) {
            $proximo = $tamanhos[0] ?? null;

            if ($proximo === null) {
                break;
            }

            if ($capacidade < $proximo) {
                continue;
            }

            $alocacoes[] = [$proximo, $capacidade];
            array_shift($tamanhos);
        }

        return $alocacoes;
    }

    /**
     * Lugares ociosos por mesa ocupada.
     *
     * @param  list<array{0: int, 1: int}>  $alocacoes  pares [pessoas, capacidade]
     */
    private function desperdicioMedio(array $alocacoes): float
    {
        if ($alocacoes === []) {
            return 0.0;
        }

        $ociosos = array_sum(array_map(fn (array $par) => $par[1] - $par[0], $alocacoes));

        return round($ociosos / count($alocacoes), 1);
    }

    /**
     * Capacidade da mesa que a entrada recebeu, ou null se ela não foi
     * promovida. Lê pelo retorno de processarPromocoes() — o contrato público —
     * em vez de reconstruir o vínculo pelo banco.
     *
     * @param  Collection<int, \App\Models\ClienteMesa>  $promovidos
     */
    private function capacidadeRecebida(Collection $promovidos, ClienteFila $entrada): ?int
    {
        $reserva = $promovidos->firstWhere('cliente_id', $entrada->cliente_id);

        return $reserva === null ? null : (int) Mesa::findOrFail($reserva->mesa_id)->capacidade;
    }
}
