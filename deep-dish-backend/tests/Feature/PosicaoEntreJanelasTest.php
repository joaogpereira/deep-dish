<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ClienteFila;
use App\Models\Fila;
use App\Models\Mesa;
use App\Models\Restaurante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A posição na fila é por RESTAURANTE, não por janela de horário.
 *
 * Existe uma 'fila' por restaurante e horário, e quem entra sem informar horário
 * cai na janela da hora corrente (`Fila::janelaAtual()`). Quando o relógio vira a
 * hora, a próxima pessoa nasce numa fila nova — e a posição, que era contada por
 * 'fila_id', dizia "você é o primeiro" com meia dúzia esperando no salão.
 *
 * Quem decide a ordem real de chamada é `FilaService::entradasAguardando`, que
 * varre todas as filas abertas do restaurante por ordem de chegada. A posição
 * mostrada tem que usar o mesmo recorte, senão ela mente para o cliente.
 */
class PosicaoEntreJanelasTest extends TestCase
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

        // Salão ocupado: ninguém é promovido e a fila fica parada para medir.
        Mesa::factory()->for($this->restaurante)->comCapacidade(4)->ocupada()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_quem_entra_na_janela_seguinte_nao_vira_primeiro_da_fila(): void
    {
        // Três pessoas na fila da hora anterior, ainda aberta.
        $anterior = $this->filaNaJanela(Fila::janelaAtual()->copy()->subHour());

        foreach ([40, 35, 30] as $ha) {
            ClienteFila::factory()->for($anterior)->entrouHa($ha)->create(['qntd_pessoas' => 2]);
        }

        // E alguém entra agora, caindo numa fila nova.
        $agora = $this->filaNaJanela(Fila::janelaAtual());
        $ultimo = ClienteFila::factory()->for($agora)->entrouHa(1)->create(['qntd_pessoas' => 2]);

        $this->assertSame(
            4,
            $ultimo->fresh()->append('posicao')->posicao,
            'Com três esperando na janela anterior, quem acabou de entrar é o quarto — não o primeiro.'
        );
    }

    /** A primeira pessoa continua sendo a primeira, com fila nova aberta ou não. */
    public function test_quem_chegou_primeiro_continua_em_primeiro(): void
    {
        $anterior = $this->filaNaJanela(Fila::janelaAtual()->copy()->subHour());
        $primeiro = ClienteFila::factory()->for($anterior)->entrouHa(40)->create(['qntd_pessoas' => 2]);

        $agora = $this->filaNaJanela(Fila::janelaAtual());
        ClienteFila::factory()->for($agora)->entrouHa(1)->create(['qntd_pessoas' => 2]);

        $this->assertSame(1, $primeiro->fresh()->append('posicao')->posicao);
    }

    /** Fila de outro restaurante não entra na conta. */
    public function test_posicao_nao_conta_fila_de_outro_restaurante(): void
    {
        $outro = Restaurante::factory()->comFilaAtiva()->create();
        $filaDoOutro = Fila::factory()->for($outro)->create(['status' => Fila::STATUS_ABERTA]);

        foreach ([40, 35] as $ha) {
            ClienteFila::factory()->for($filaDoOutro)->entrouHa($ha)->create(['qntd_pessoas' => 2]);
        }

        $minha = $this->filaNaJanela(Fila::janelaAtual());
        $entrada = ClienteFila::factory()->for($minha)->entrouHa(10)->create(['qntd_pessoas' => 2]);

        $this->assertSame(1, $entrada->fresh()->append('posicao')->posicao);
    }

    /**
     * O painel numerava por janela: mostrava 1,2,3 e depois 1,2,3 de novo, como se
     * houvesse duas filas de espera. É uma fila só.
     */
    public function test_painel_numera_a_fila_de_forma_continua(): void
    {
        $anterior = $this->filaNaJanela(Fila::janelaAtual()->copy()->subHour());

        foreach ([40, 35] as $ha) {
            ClienteFila::factory()->for($anterior)->entrouHa($ha)->create(['qntd_pessoas' => 2]);
        }

        $agora = $this->filaNaJanela(Fila::janelaAtual());

        foreach ([10, 5] as $ha) {
            ClienteFila::factory()->for($agora)->entrouHa($ha)->create(['qntd_pessoas' => 2]);
        }

        $resposta = $this->comoRestaurante()->getJson('/api/restaurante/fila')->assertOk();

        $this->assertSame([1, 2, 3, 4], array_column($resposta->json(), 'posicao'));
    }

    /** Fila aberta numa janela específica, que é como `enfileirar` as cria. */
    private function filaNaJanela(Carbon $janela): Fila
    {
        return Fila::factory()->for($this->restaurante)->create([
            'horario_reserva' => $janela,
            'status' => Fila::STATUS_ABERTA,
        ]);
    }

    private function comoRestaurante(): static
    {
        return $this->withHeader('Authorization', 'Bearer '.auth('restaurante')->login($this->restaurante));
    }
}
