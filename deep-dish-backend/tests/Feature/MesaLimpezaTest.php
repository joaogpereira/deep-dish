<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ClienteFila;
use App\Models\ClienteMesa;
use App\Models\Fila;
use App\Models\Mesa;
use App\Models\MesaLimpeza;
use App\Models\Restaurante;
use App\Services\FilaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Estados intermediários de mesa (#171).
 *
 * O intervalo entre "cliente saiu" e "mesa pronta" era invisível: 'liberar'
 * devolvia a mesa para 'livre' no mesmo instante. Agora a mesa passa por
 * 'aguardando_limpeza' e 'em_limpeza', e cada passagem grava em 'mesa_limpeza'.
 *
 * O preço é que duas ações humanas passam a separar a saída do cliente da mesa
 * voltar à fila — daí a liberação automática por tolerância, coberta aqui.
 */
class MesaLimpezaTest extends TestCase
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ───────────── Quem pede limpeza e quem não pede ─────────────

    /**
     * A regra inteira: 'ocupada' só é escrito no check-in, então significa que
     * alguém sentou. No-show não passa por 'ocupada' e não suja mesa.
     */
    public function test_no_show_nao_manda_a_mesa_para_limpeza(): void
    {
        $mesa = $this->mesa(4, Mesa::STATUS_LIVRE);
        $reserva = ClienteMesa::factory()->for($mesa, 'mesa')->create([
            'status' => 'confirmada',
            'horario_checkin' => null,
        ]);

        $this->comoRestaurante()
            ->patchJson("/api/restaurante/reservas/{$reserva->id}/liberar")
            ->assertOk();

        $this->assertSame(Mesa::STATUS_LIVRE, $mesa->fresh()->status);
        $this->assertSame(0, MesaLimpeza::where('mesa_id', $mesa->id)->count());
    }

    public function test_saida_apos_checkin_abre_ciclo_de_limpeza(): void
    {
        $mesa = $this->mesa(4, Mesa::STATUS_OCUPADA);
        $reserva = ClienteMesa::factory()->for($mesa, 'mesa')->emAndamento()->create();

        $this->comoRestaurante()
            ->patchJson("/api/restaurante/reservas/{$reserva->id}/liberar")
            ->assertOk();

        $this->assertSame(Mesa::STATUS_AGUARDANDO_LIMPEZA, $mesa->fresh()->status);

        $ciclo = MesaLimpeza::where('mesa_id', $mesa->id)->sole();
        $this->assertNotNull($ciclo->aguardando_desde);
        $this->assertNull($ciclo->iniciada_em);
        $this->assertNull($ciclo->finalizada_em);
    }

    // ───────────── O que a limpeza faz com a fila ─────────────

    public function test_mesa_em_limpeza_nao_e_candidata_a_promocao(): void
    {
        $this->mesa(4, Mesa::STATUS_AGUARDANDO_LIMPEZA, 1);
        $this->mesa(4, Mesa::STATUS_EM_LIMPEZA, 2);
        $esperando = $this->naFila(2);

        $promovidos = app(FilaService::class)->processarPromocoes($this->restaurante->id);

        $this->assertCount(0, $promovidos);
        $this->assertNull(ClienteFila::withTrashed()->findOrFail($esperando->id)->status_saida);
    }

    /**
     * O outro lado: mesa em limpeza continua contando na capacidade do salão.
     * 'enfileirar' recusa grupo maior que a maior mesa, e uma mesa que está só
     * suja vai voltar — recusar o grupo por causa dela seria errado.
     */
    public function test_mesa_em_limpeza_ainda_conta_na_capacidade_do_salao(): void
    {
        $this->mesa(8, Mesa::STATUS_EM_LIMPEZA);

        $this->comoCliente(Cliente::factory()->create())
            ->postJson('/api/fila', [
                'restaurante_id' => $this->restaurante->id,
                'qntd_pessoas' => 8,
            ])
            ->assertCreated();
    }

    // ───────────── Transições pelo painel ─────────────

    public function test_fluxo_completo_pelo_painel(): void
    {
        $mesa = $this->mesa(4, Mesa::STATUS_AGUARDANDO_LIMPEZA);
        $this->abrirCiclo($mesa);

        $this->comoRestaurante()
            ->putJson("/api/restaurante/mesas/{$mesa->id}", ['status' => Mesa::STATUS_EM_LIMPEZA])
            ->assertOk();

        $this->assertSame(Mesa::STATUS_EM_LIMPEZA, $mesa->fresh()->status);
        $this->assertNotNull(MesaLimpeza::where('mesa_id', $mesa->id)->sole()->iniciada_em);

        $this->comoRestaurante()
            ->putJson("/api/restaurante/mesas/{$mesa->id}", ['status' => Mesa::STATUS_LIVRE])
            ->assertOk();

        $this->assertSame(Mesa::STATUS_LIVRE, $mesa->fresh()->status);

        $ciclo = MesaLimpeza::where('mesa_id', $mesa->id)->sole();
        $this->assertNotNull($ciclo->finalizada_em);
        $this->assertFalse($ciclo->liberada_automaticamente);
    }

    /**
     * Pular o 'em_limpeza' é legítimo ("já estava limpa"), e aí não há duração para
     * cronometrar — um zero mentiria na média.
     */
    public function test_atalho_para_livre_fecha_o_ciclo_sem_duracao(): void
    {
        $mesa = $this->mesa(4, Mesa::STATUS_AGUARDANDO_LIMPEZA);
        $this->abrirCiclo($mesa);

        $this->comoRestaurante()
            ->putJson("/api/restaurante/mesas/{$mesa->id}", ['status' => Mesa::STATUS_LIVRE])
            ->assertOk();

        $ciclo = MesaLimpeza::where('mesa_id', $mesa->id)->sole();
        $this->assertNotNull($ciclo->finalizada_em);
        $this->assertNull($ciclo->duracao_segundos);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function transicoesIlegais(): array
    {
        return [
            'livre nao vai direto para em_limpeza' => [Mesa::STATUS_LIVRE, Mesa::STATUS_EM_LIMPEZA],
            'ocupada nao e liberada na mao' => [Mesa::STATUS_OCUPADA, Mesa::STATUS_LIVRE],
            'painel nao suja mesa' => [Mesa::STATUS_LIVRE, Mesa::STATUS_AGUARDANDO_LIMPEZA],
            'limpeza nao volta um passo' => [Mesa::STATUS_EM_LIMPEZA, Mesa::STATUS_AGUARDANDO_LIMPEZA],
        ];
    }

    #[DataProvider('transicoesIlegais')]
    public function test_transicao_ilegal_e_recusada(string $de, string $para): void
    {
        $mesa = $this->mesa(4, $de);

        $this->comoRestaurante()
            ->putJson("/api/restaurante/mesas/{$mesa->id}", ['status' => $para])
            ->assertStatus(422);

        $this->assertSame($de, $mesa->fresh()->status);
    }

    /**
     * Regressão do impasse que a #171 encontrou.
     *
     * O guard de reserva ativa barrava QUALQUER mudança de status. Como mesa em
     * limpeza continua sendo oferecida para reserva futura (e isso está certo —
     * limpar agora não impede reservar para as 20h), bastava alguém reservar para a
     * noite e o restaurante não conseguia mais concluir a limpeza: a mesa só saía
     * pela liberação automática.
     */
    public function test_reserva_futura_ativa_nao_impede_concluir_a_limpeza(): void
    {
        $mesa = $this->mesa(4, Mesa::STATUS_EM_LIMPEZA);
        $this->abrirCiclo($mesa);

        ClienteMesa::factory()->for($mesa, 'mesa')->create([
            'status' => 'confirmada',
            'horario_reserva' => now()->utc()->addHours(6),
        ]);

        $this->comoRestaurante()
            ->putJson("/api/restaurante/mesas/{$mesa->id}", ['status' => Mesa::STATUS_LIVRE])
            ->assertOk();

        $this->assertSame(Mesa::STATUS_LIVRE, $mesa->fresh()->status);
    }

    /** O guard continua valendo para o que ele protegia: tirar a mesa de operação. */
    public function test_reserva_ativa_ainda_impede_bloquear_a_mesa(): void
    {
        $mesa = $this->mesa(4, Mesa::STATUS_LIVRE);

        ClienteMesa::factory()->for($mesa, 'mesa')->create([
            'status' => 'confirmada',
            'horario_reserva' => now()->utc()->addHours(6),
        ]);

        $this->comoRestaurante()
            ->putJson("/api/restaurante/mesas/{$mesa->id}", ['status' => Mesa::STATUS_BLOQUEADA])
            ->assertStatus(422);

        $this->assertSame(Mesa::STATUS_LIVRE, $mesa->fresh()->status);
    }

    // ───────────── Liberação automática ─────────────

    public function test_comando_libera_mesa_vencida_e_manda_a_fila_andar(): void
    {
        $mesa = $this->mesa(4, Mesa::STATUS_OCUPADA);
        $reserva = ClienteMesa::factory()->for($mesa, 'mesa')->emAndamento()->create();
        $esperando = $this->naFila(2);

        $this->comoRestaurante()
            ->patchJson("/api/restaurante/reservas/{$reserva->id}/liberar")
            ->assertOk();

        $this->assertSame(Mesa::STATUS_AGUARDANDO_LIMPEZA, $mesa->fresh()->status);

        // Antes da tolerância, nada acontece.
        $this->artisan('mesas:liberar-limpeza')->expectsOutput('Mesas liberadas: 0')->assertSuccessful();

        $this->avancarMinutos((int) config('mesa.tolerancia_limpeza_minutos') + 1);

        $this->artisan('mesas:liberar-limpeza')->expectsOutput('Mesas liberadas: 1')->assertSuccessful();

        $this->assertSame(Mesa::STATUS_LIVRE, $mesa->fresh()->status);
        $this->assertNotNull(ClienteFila::withTrashed()->findOrFail($esperando->id)->status_saida);
    }

    public function test_comando_respeita_a_tolerancia_configurada(): void
    {
        config(['mesa.tolerancia_limpeza_minutos' => 30]);

        $mesa = $this->mesa(4, Mesa::STATUS_AGUARDANDO_LIMPEZA);
        $this->abrirCiclo($mesa);

        $this->avancarMinutos(10);
        $this->artisan('mesas:liberar-limpeza')->expectsOutput('Mesas liberadas: 0')->assertSuccessful();

        $this->avancarMinutos(25);
        $this->artisan('mesas:liberar-limpeza')->expectsOutput('Mesas liberadas: 1')->assertSuccessful();
    }

    public function test_comando_e_idempotente(): void
    {
        $mesa = $this->mesa(4, Mesa::STATUS_AGUARDANDO_LIMPEZA);
        $this->abrirCiclo($mesa);
        $this->avancarMinutos(10);

        $this->artisan('mesas:liberar-limpeza')->expectsOutput('Mesas liberadas: 1')->assertSuccessful();
        $antes = MesaLimpeza::where('mesa_id', $mesa->id)
            ->get(['finalizada_em', 'liberada_automaticamente'])->toArray();

        $this->artisan('mesas:liberar-limpeza')->expectsOutput('Mesas liberadas: 0')->assertSuccessful();
        $depois = MesaLimpeza::where('mesa_id', $mesa->id)
            ->get(['finalizada_em', 'liberada_automaticamente'])->toArray();

        $this->assertSame($antes, $depois);
    }

    public function test_ciclo_liberado_pelo_comando_fica_marcado(): void
    {
        $mesa = $this->mesa(4, Mesa::STATUS_AGUARDANDO_LIMPEZA);
        $this->abrirCiclo($mesa);
        $this->avancarMinutos(10);

        $this->artisan('mesas:liberar-limpeza')->assertSuccessful();

        $ciclo = MesaLimpeza::where('mesa_id', $mesa->id)->sole();
        $this->assertTrue($ciclo->liberada_automaticamente);
        // Ninguém limpou: sem 'iniciada_em' não há duração para cronometrar, e um
        // zero entraria na média como se tivesse havido limpeza.
        $this->assertNull($ciclo->duracao_segundos);
    }

    public function test_comando_esta_agendado(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('mesas:liberar-limpeza')
            ->assertSuccessful();
    }

    // ───────────── Defaults do banco ─────────────

    /**
     * O único teste que prova a migration de defaults. Antes, uma mesa inserida sem
     * status nascia 'disponível' — valor que nenhuma consulta do código encontra.
     */
    public function test_mesa_inserida_sem_status_nasce_livre(): void
    {
        DB::insert(
            'INSERT INTO mesa (id, restaurante_id, numero, capacidade, created_at, updated_at)
             VALUES (?, ?, ?, ?, now(), now())',
            [(string) Str::uuid(), $this->restaurante->id, 99, 4]
        );

        $this->assertSame(Mesa::STATUS_LIVRE, Mesa::where('numero', 99)->sole()->status);
    }

    // ───────────── Helpers ─────────────

    private function mesa(int $capacidade, string $status = Mesa::STATUS_LIVRE, int $numero = 1): Mesa
    {
        return Mesa::factory()->for($this->restaurante)->create([
            'capacidade' => $capacidade,
            'status' => $status,
            'numero' => $numero,
        ]);
    }

    /** Ciclo em aberto para mesa que a factory já criou em limpeza. */
    private function abrirCiclo(Mesa $mesa): MesaLimpeza
    {
        return MesaLimpeza::create([
            'mesa_id' => $mesa->id,
            'aguardando_desde' => now(),
        ]);
    }

    private function naFila(int $pessoas = 2, int $entrouHa = 10): ClienteFila
    {
        return ClienteFila::factory()
            ->for($this->fila)
            ->entrouHa($entrouHa)
            ->create(['qntd_pessoas' => $pessoas]);
    }

    private function avancarMinutos(int $minutos): void
    {
        Carbon::setTestNow(now()->addMinutes($minutos));
    }

    private function comoCliente(Cliente $cliente): static
    {
        return $this->withHeader('Authorization', 'Bearer '.auth('api')->login($cliente));
    }

    private function comoRestaurante(): static
    {
        return $this->withHeader('Authorization', 'Bearer '.auth('restaurante')->login($this->restaurante));
    }
}
