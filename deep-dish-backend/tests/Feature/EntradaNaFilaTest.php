<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ClienteFila;
use App\Models\Mesa;
use App\Models\Restaurante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Entrada na fila: "cheguei agora, me põe na lista".
 *
 * Quem escolhe data e hora é reserva. A tela do cliente não pede horário para a
 * fila — e antes pedia, o que prendia o fluxo: com as reservas desligadas, o
 * seletor de data/hora nem aparecia e ninguém conseguia entrar na fila.
 *
 * Também recusa entrada em fila que não teria como andar: salão inteiro
 * bloqueado, ou grupo maior que a maior mesa em operação.
 */
class EntradaNaFilaTest extends TestCase
{
    use RefreshDatabase;

    private Restaurante $restaurante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurante = Restaurante::factory()->comFilaAtiva()->create([
            'horario_abertura' => '00:00',
            'horario_fechamento' => '23:59',
        ]);
    }

    public function test_entra_na_fila_sem_informar_horario(): void
    {
        $this->mesa(4, 'ocupada');

        $resposta = $this->comoCliente()
            ->postJson('/api/fila', [
                'restaurante_id' => $this->restaurante->id,
                'qntd_pessoas' => 2,
            ]);

        $resposta->assertCreated();
        $resposta->assertJsonPath('data.posicao', 1);
        $this->assertDatabaseCount('clientefila', 1);
    }

    public function test_quem_chega_na_mesma_hora_divide_a_fila_e_a_posicao_anda(): void
    {
        $this->mesa(4, 'ocupada');

        $primeira = $this->entrar(2);
        $segunda = $this->entrar(2);

        $this->assertSame(1, $primeira->json('data.posicao'));
        $this->assertSame(2, $segunda->json('data.posicao'), 'Cada um caiu numa fila própria: a posição perde o sentido.');
        $this->assertSame(
            $primeira->json('data.fila_id'),
            $segunda->json('data.fila_id'),
            'Quem chega na mesma janela tem que compartilhar a mesma fila.'
        );
    }

    public function test_janela_e_a_hora_corrente_e_nao_o_instante_do_clique(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 19:37:42', 'UTC'));
        $this->mesa(4, 'ocupada');

        $this->entrar(2);

        $fila = ClienteFila::firstOrFail()->fila;
        $this->assertSame('2026-10-01 19:00:00', $fila->horario_reserva->utc()->format('Y-m-d H:i:s'));
    }

    public function test_horario_explicito_continua_aceito(): void
    {
        $this->mesa(4, 'ocupada');
        $futuro = now()->addHours(3)->utc();

        $resposta = $this->comoCliente()->postJson('/api/fila', [
            'restaurante_id' => $this->restaurante->id,
            'horario_reserva' => $futuro->toIso8601String(),
            'qntd_pessoas' => 2,
        ]);

        $resposta->assertCreated();
        $this->assertSame(
            $futuro->format('Y-m-d H:i'),
            ClienteFila::firstOrFail()->fila->horario_reserva->utc()->format('Y-m-d H:i')
        );
    }

    // ───────── Fila que não teria como andar ─────────

    public function test_salao_inteiro_bloqueado_recusa_entrada(): void
    {
        $this->mesa(4, 'bloqueada');
        $this->mesa(6, 'bloqueada', 2);

        $this->entrar(2)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Este restaurante não está recebendo fila no momento.');

        $this->assertDatabaseCount('clientefila', 0);
    }

    public function test_restaurante_sem_mesas_recusa_entrada(): void
    {
        $this->entrar(2)->assertStatus(422);
    }

    public function test_grupo_maior_que_a_maior_mesa_recusa_entrada(): void
    {
        $this->mesa(4, 'ocupada');

        $this->entrar(8)
            ->assertStatus(422)
            ->assertJsonPath('message', 'A maior mesa deste restaurante comporta 4 pessoas.');
    }

    public function test_mesa_ocupada_conta_como_operacao(): void
    {
        // Mesa cheia é o motivo de existir fila: não pode bloquear a entrada.
        $this->mesa(4, 'ocupada');

        $this->entrar(4)->assertCreated();
    }

    // ───────────────────── Helpers ─────────────────────

    private function mesa(int $capacidade, string $status, int $numero = 1): Mesa
    {
        return Mesa::factory()->for($this->restaurante)->create([
            'capacidade' => $capacidade,
            'status' => $status,
            'numero' => $numero,
        ]);
    }

    /**
     * Teto de sanidade no tamanho do grupo.
     *
     * O que barrava numero absurdo aqui era so a regra de negocio ("a maior mesa
     * comporta N"), e ela e um acidente felizo: quem refatorasse aquela checagem
     * abriria a porta. assertJsonValidationErrors distingue os dois caminhos — a
     * recusa por capacidade devolve 'message' sem a chave 'errors'.
     */
    public function test_grupo_acima_do_teto_e_recusado_pela_validacao(): void
    {
        $this->mesa(4, 'livre');

        $this->entrar(101)
            ->assertStatus(422)
            ->assertJsonValidationErrors('qntd_pessoas');
    }

    /** O teto nao pode apertar quem e plausivel: 100 passa pela validacao. */
    public function test_teto_nao_recusa_grupo_plausivel(): void
    {
        $this->mesa(100, 'livre');

        $this->entrar(100)->assertCreated();
    }

    private function entrar(int $pessoas)
    {
        return $this->comoCliente()->postJson('/api/fila', [
            'restaurante_id' => $this->restaurante->id,
            'qntd_pessoas' => $pessoas,
        ]);
    }

    private function comoCliente(): static
    {
        return $this->withHeader('Authorization', 'Bearer '.auth('api')->login(Cliente::factory()->create()));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
