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
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Ponto único de promoção da fila (#168).
 *
 * Antes, só o "liberar" do painel chamava alguém. Cancelar reserva, expirar pelo
 * cron, desbloquear ou criar mesa devolviam lugar e a fila continuava parada —
 * o bug de produto mais grave do sistema. Estes testes cobrem cada um desses
 * caminhos e as duas regras que a promoção passou a respeitar: a flag
 * 'fila_ativa' e a disponibilidade real da mesa.
 *
 * "Mesa livre" não basta: entre a chamada e o check-in a mesa continua 'livre',
 * e ela pode ter reserva marcada para daqui a pouco.
 */
class FilaPromocaoTest extends TestCase
{
    use RefreshDatabase;

    private Restaurante $restaurante;

    private Fila $fila;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurante = Restaurante::factory()->comFilaAtiva()->create();
        $this->fila = Fila::factory()->for($this->restaurante)->create();
    }

    // ───────────────── Caminhos que liberam lugar ─────────────────

    public function test_cancelar_reserva_com_checkin_libera_a_mesa_e_chama_a_fila(): void
    {
        $mesa = $this->mesa(4, 'ocupada');
        $cliente = Cliente::factory()->create();
        $reserva = ClienteMesa::factory()
            ->for($cliente, 'cliente')->for($mesa, 'mesa')
            ->emAndamento()->create();
        $esperando = $this->naFila(2);

        $this->comoCliente($cliente)
            ->deleteJson("/api/reservas/{$reserva->id}")
            ->assertOk();

        $this->assertChamado($esperando, $mesa);
        $this->assertSame('livre', $mesa->fresh()->status);
    }

    public function test_reservas_expirar_chama_a_fila(): void
    {
        $mesa = $this->mesa(4);
        // No-show: reserva confirmada cujo horário passou da tolerância.
        ClienteMesa::factory()->for($mesa, 'mesa')->confirmada()->create([
            'horario_reserva' => now()->utc()->subHours(3),
        ]);
        $esperando = $this->naFila(2);

        $this->artisan('reservas:expirar')->assertSuccessful();

        $this->assertChamado($esperando, $mesa);
    }

    public function test_liberar_pelo_painel_chama_a_fila(): void
    {
        $mesa = $this->mesa(4, 'ocupada');
        $reserva = ClienteMesa::factory()->for($mesa, 'mesa')->emAndamento()->create();
        $esperando = $this->naFila(2);

        $this->comoRestaurante()
            ->patchJson("/api/restaurante/reservas/{$reserva->id}/liberar")
            ->assertOk();

        $this->assertChamado($esperando, $mesa);
    }

    public function test_desbloquear_mesa_chama_a_fila(): void
    {
        $mesa = $this->mesa(4, 'bloqueada');
        $esperando = $this->naFila(2);

        $this->comoRestaurante()
            ->putJson("/api/restaurante/mesas/{$mesa->id}", ['status' => 'livre'])
            ->assertOk();

        $this->assertChamado($esperando, $mesa);
    }

    public function test_criar_mesa_chama_a_fila(): void
    {
        $esperando = $this->naFila(2);

        $this->comoRestaurante()
            ->postJson('/api/restaurante/mesas', ['numero' => 7, 'capacidade' => 4])
            ->assertCreated();

        $this->assertChamado($esperando, Mesa::where('numero', 7)->firstOrFail());
    }

    public function test_comando_do_agendador_chama_a_fila(): void
    {
        $mesa = $this->mesa(4);
        $esperando = $this->naFila(2);

        $this->artisan('fila:promover')->assertSuccessful();

        $this->assertChamado($esperando, $mesa);
    }

    public function test_comando_esta_agendado(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('fila:promover')
            ->assertSuccessful();
    }

    // ───────────────────── Regras da promoção ─────────────────────

    public function test_fila_desligada_nao_promove_ninguem(): void
    {
        $desligado = Restaurante::factory()->create(); // fila_ativa = false
        $fila = Fila::factory()->for($desligado)->create();
        $entrada = ClienteFila::factory()->for($fila)->entrouHa(10)->create(['qntd_pessoas' => 2]);
        Mesa::factory()->for($desligado)->create(['capacidade' => 4, 'status' => 'livre']);

        $promovidos = app(FilaService::class)->processarPromocoes($desligado->id);

        $this->assertCount(0, $promovidos);
        $this->assertAindaEsperando($entrada);
    }

    public function test_entrar_em_fila_desligada_devolve_422(): void
    {
        $desligado = Restaurante::factory()->create();

        $this->comoCliente(Cliente::factory()->create())
            ->postJson('/api/fila', [
                'restaurante_id' => $desligado->id,
                'horario_reserva' => now()->addHour()->toIso8601String(),
                'qntd_pessoas' => 2,
            ])
            ->assertStatus(422);
    }

    public function test_mesa_com_chamada_pendente_nao_recebe_um_segundo_cliente(): void
    {
        $mesa = $this->mesa(4);
        $primeiro = $this->naFila(2, 20);
        $segundo = $this->naFila(2, 10);

        $servico = app(FilaService::class);

        $this->assertCount(1, $servico->processarPromocoes($this->restaurante->id));
        // A mesa segue 'livre' até o check-in: é a reserva da chamada que a segura.
        $this->assertSame('livre', $mesa->fresh()->status);

        $this->assertCount(0, $servico->processarPromocoes($this->restaurante->id));

        $this->assertChamado($primeiro, $mesa);
        $this->assertAindaEsperando($segundo);
    }

    public function test_mesa_com_reserva_na_proxima_hora_nao_e_candidata(): void
    {
        $mesa = $this->mesa(4);
        ClienteMesa::factory()->for($mesa, 'mesa')->confirmada()->create([
            'horario_reserva' => now()->utc()->addMinutes(30),
        ]);
        $esperando = $this->naFila(2);

        $promovidos = app(FilaService::class)->processarPromocoes($this->restaurante->id);

        $this->assertCount(0, $promovidos);
        $this->assertAindaEsperando($esperando);
    }

    public function test_reserva_depois_da_janela_nao_atrapalha(): void
    {
        $mesa = $this->mesa(4);
        ClienteMesa::factory()->for($mesa, 'mesa')->confirmada()->create([
            'horario_reserva' => now()->utc()->addHours(3),
        ]);
        $esperando = $this->naFila(2);

        $this->assertCount(1, app(FilaService::class)->processarPromocoes($this->restaurante->id));
        $this->assertChamado($esperando, $mesa);
    }

    public function test_promove_varias_mesas_na_ordem_de_chegada(): void
    {
        $primeiraMesa = $this->mesa(4, 'livre', 1);
        $segundaMesa = $this->mesa(4, 'livre', 2);
        $primeiro = $this->naFila(2, 30);
        $segundo = $this->naFila(2, 20);
        $terceiro = $this->naFila(2, 10);

        $promovidos = app(FilaService::class)->processarPromocoes($this->restaurante->id);

        $this->assertCount(2, $promovidos);
        $this->assertChamado($primeiro, $primeiraMesa);
        $this->assertChamado($segundo, $segundaMesa);
        $this->assertAindaEsperando($terceiro);
    }

    // ───────────────────────── Helpers ─────────────────────────

    private function mesa(int $capacidade, string $status = 'livre', int $numero = 1): Mesa
    {
        return Mesa::factory()->for($this->restaurante)->create([
            'capacidade' => $capacidade,
            'status' => $status,
            'numero' => $numero,
        ]);
    }

    private function naFila(int $pessoas = 2, int $entrouHa = 10): ClienteFila
    {
        return ClienteFila::factory()
            ->for($this->fila)
            ->entrouHa($entrouHa)
            ->create(['qntd_pessoas' => $pessoas]);
    }

    private function comoCliente(Cliente $cliente): static
    {
        return $this->withHeader('Authorization', 'Bearer '.auth('api')->login($cliente));
    }

    private function comoRestaurante(): static
    {
        return $this->withHeader('Authorization', 'Bearer '.auth('restaurante')->login($this->restaurante));
    }

    private function assertChamado(ClienteFila $entrada, Mesa $mesa): void
    {
        $saida = ClienteFila::withTrashed()->findOrFail($entrada->id);

        $this->assertSame(
            ClienteFila::STATUS_SAIDA_ATENDIDO,
            $saida->status_saida,
            'A entrada continua na fila — a promoção não rodou neste caminho.'
        );
        $this->assertDatabaseHas('clientemesa', [
            'cliente_id' => $entrada->cliente_id,
            'mesa_id' => $mesa->id,
            'status' => 'confirmada',
        ]);
    }

    private function assertAindaEsperando(ClienteFila $entrada): void
    {
        $this->assertNull(
            ClienteFila::withTrashed()->findOrFail($entrada->id)->status_saida,
            'A entrada foi chamada quando não deveria.'
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
