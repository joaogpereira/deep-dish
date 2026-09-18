<?php

namespace Tests\Feature;

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
 * fila:expirar-chamados (#164) — detecção automática de desistência na fila.
 *
 * "Chamado" é a promoção para a mesa: FilaService::promoverProximoParaMesa cria
 * a reserva, grava 'chamado_em' e fecha a entrada como 'atendido'. Esse
 * atendido é provisório até o check-in; sem check-in dentro da tolerância, o
 * command o reclassifica como 'expirado' e passa a mesa para o próximo.
 *
 * O relógio é avançado com Carbon::setTestNow() — o mesmo now() que o command
 * usa para calcular o limite da tolerância.
 */
class ExpirarChamadosTest extends TestCase
{
    use RefreshDatabase;

    private Restaurante $restaurante;

    private Mesa $mesa;

    private Fila $fila;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurante = Restaurante::factory()->create();
        $this->mesa = Mesa::factory()->for($this->restaurante)->create(['capacidade' => 4]);
        $this->fila = Fila::factory()->for($this->restaurante)->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ─── Critério: chamada não confirmada vira 'expirado' ────

    public function test_chamado_sem_check_in_apos_a_tolerancia_vira_expirado_e_mesa_vai_para_o_proximo(): void
    {
        $chamado = $this->entrada(entrouHa: 30);
        $proximo = $this->entrada(entrouHa: 20);

        $reserva = $this->chamarProximo();

        $depoisDaChamada = ClienteFila::withTrashed()->findOrFail($chamado->id);
        $this->assertSame(ClienteFila::STATUS_SAIDA_ATENDIDO, $depoisDaChamada->status_saida);
        $this->assertNotNull($depoisDaChamada->chamado_em, 'A promoção precisa gravar chamado_em.');
        $this->assertSame($reserva->id, $depoisDaChamada->clientemesa_id, 'A entrada precisa apontar para a reserva da chamada.');

        $this->avancarMinutos(16);
        $this->artisan('fila:expirar-chamados')
            ->expectsOutput('Chamados expirados: 1')
            ->assertSuccessful();

        $expirado = ClienteFila::withTrashed()->findOrFail($chamado->id);
        $this->assertSame(ClienteFila::STATUS_SAIDA_EXPIRADO, $expirado->status_saida);
        // A espera até a chamada foi real e continua medida como estava.
        $this->assertEquals($depoisDaChamada->saiu_em, $expirado->saiu_em);
        $this->assertSame($depoisDaChamada->tempo_espera_segundos, $expirado->tempo_espera_segundos);

        $reserva->refresh();
        $this->assertSame('expirada', $reserva->status);
        $this->assertNotNull($reserva->horario_saida);
        $this->assertNull($reserva->duracao_segundos, 'No-show não sentou: duração fica NULL, não 0.');

        // A mesa foi para o próximo da fila.
        $promovido = ClienteFila::withTrashed()->findOrFail($proximo->id);
        $this->assertSame(ClienteFila::STATUS_SAIDA_ATENDIDO, $promovido->status_saida);
        $this->assertNotNull($promovido->clientemesa_id);
        $this->assertSame($this->mesa->id, $promovido->reservaDaChamada->mesa_id);
        $this->assertSame('confirmada', $promovido->reservaDaChamada->status);
    }

    public function test_dentro_da_tolerancia_nao_expira(): void
    {
        $chamado = $this->entrada(entrouHa: 30);
        $reserva = $this->chamarProximo();

        $this->avancarMinutos(14);
        $this->artisan('fila:expirar-chamados')->expectsOutput('Chamados expirados: 0');

        $this->assertSame(ClienteFila::STATUS_SAIDA_ATENDIDO, ClienteFila::withTrashed()->findOrFail($chamado->id)->status_saida);
        $this->assertSame('confirmada', $reserva->fresh()->status);
    }

    public function test_chamado_que_fez_check_in_nao_expira(): void
    {
        $chamado = $this->entrada(entrouHa: 30);
        $reserva = $this->chamarProximo();

        $this->avancarMinutos(5);
        $reserva->update(['status' => 'em_andamento', 'horario_checkin' => now()]);

        $this->avancarMinutos(60);
        $this->artisan('fila:expirar-chamados')->expectsOutput('Chamados expirados: 0');

        $this->assertSame(ClienteFila::STATUS_SAIDA_ATENDIDO, ClienteFila::withTrashed()->findOrFail($chamado->id)->status_saida);
        $this->assertSame('em_andamento', $reserva->fresh()->status);
    }

    public function test_tolerancia_vem_da_configuracao(): void
    {
        config(['fila.tolerancia_chamada_minutos' => 5]);

        $chamado = $this->entrada(entrouHa: 30);
        $this->chamarProximo();

        $this->avancarMinutos(6);
        $this->artisan('fila:expirar-chamados')->expectsOutput('Chamados expirados: 1');

        $this->assertSame(ClienteFila::STATUS_SAIDA_EXPIRADO, ClienteFila::withTrashed()->findOrFail($chamado->id)->status_saida);
    }

    // ─── Critério: entrada não chamada NUNCA expira ──────────

    public function test_entrada_nao_chamada_nunca_expira(): void
    {
        // Esperando há horas, sem nunca ter sido chamada.
        $esperando = $this->entrada(entrouHa: 180);

        // 'atendido' anterior a este command: sem chamado_em, não é provisório.
        $atendidoSemChamada = ClienteFila::factory()->for($this->fila)->atendido(esperouMinutos: 40)->create();

        $this->avancarMinutos(24 * 60);
        $this->artisan('fila:expirar-chamados')->expectsOutput('Chamados expirados: 0');

        $esperando->refresh();
        $this->assertNull($esperando->status_saida);
        $this->assertNull($esperando->deleted_at);

        $this->assertSame(
            ClienteFila::STATUS_SAIDA_ATENDIDO,
            ClienteFila::withTrashed()->findOrFail($atendidoSemChamada->id)->status_saida
        );
    }

    // ─── Critério: idempotência ──────────────────────────────

    public function test_command_e_idempotente(): void
    {
        $chamado = $this->entrada(entrouHa: 30);
        $this->entrada(entrouHa: 20);
        $this->chamarProximo();

        $this->avancarMinutos(16);
        $this->artisan('fila:expirar-chamados')->expectsOutput('Chamados expirados: 1');

        $estadoFila = ClienteFila::withTrashed()->orderBy('id')->get(['id', 'status_saida', 'chamado_em', 'clientemesa_id'])->toArray();
        $estadoReservas = ClienteMesa::withTrashed()->orderBy('id')->get(['id', 'status', 'horario_saida'])->toArray();

        $this->artisan('fila:expirar-chamados')->expectsOutput('Chamados expirados: 0');

        $this->assertSame($estadoFila, ClienteFila::withTrashed()->orderBy('id')->get(['id', 'status_saida', 'chamado_em', 'clientemesa_id'])->toArray());
        $this->assertSame($estadoReservas, ClienteMesa::withTrashed()->orderBy('id')->get(['id', 'status', 'horario_saida'])->toArray());
        $this->assertSame(ClienteFila::STATUS_SAIDA_EXPIRADO, ClienteFila::withTrashed()->findOrFail($chamado->id)->status_saida);
    }

    // ─── Mesa ─────────────────────────────────────────────────

    public function test_mesa_bloqueada_depois_da_chamada_nao_recebe_o_proximo(): void
    {
        $chamado = $this->entrada(entrouHa: 30);
        $proximo = $this->entrada(entrouHa: 20);
        $this->chamarProximo();

        $this->mesa->update(['status' => 'bloqueada']);

        $this->avancarMinutos(16);
        $this->artisan('fila:expirar-chamados')->expectsOutput('Chamados expirados: 1');

        $this->assertSame(ClienteFila::STATUS_SAIDA_EXPIRADO, ClienteFila::withTrashed()->findOrFail($chamado->id)->status_saida);
        $this->assertNull($proximo->fresh()->status_saida, 'Mesa bloqueada não pode ser entregue ao próximo.');
    }

    // ─── Critério: agendamento ───────────────────────────────

    public function test_agendamento_aparece_no_schedule_list(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('fila:expirar-chamados')
            ->assertSuccessful();
    }

    // ───────────────────────── Helpers ─────────────────────────

    private function entrada(int $entrouHa): ClienteFila
    {
        return ClienteFila::factory()->for($this->fila)->entrouHa($entrouHa)->create(['qntd_pessoas' => 2]);
    }

    /** A chamada de produção: a mesa livre vai para o primeiro da fila. */
    private function chamarProximo(): ClienteMesa
    {
        $reserva = app(FilaService::class)->promoverProximoParaMesa($this->restaurante->id, $this->mesa);

        $this->assertNotNull($reserva, 'A promoção não chamou ninguém — checar capacidade/qntd_pessoas.');

        return $reserva;
    }

    private function avancarMinutos(int $minutos): void
    {
        Carbon::setTestNow(now()->addMinutes($minutos));
    }
}
