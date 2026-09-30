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
}
