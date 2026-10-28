<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ClienteFila;
use App\Models\ClienteMesa;
use App\Models\Fila;
use App\Models\Mesa;
use App\Models\Restaurante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Fluxos de ponta a ponta pela API HTTP, sem chamar o serviço direto: o que o
 * painel e o app fazem de verdade (#174 e #170).
 *
 *  A — QR: painel gera, cliente abre a URL, loga e entra na fila.
 *  B — Encaixe: dois grupos, uma mesa de 10 criada pelo painel.
 *  C — Anti-starvation: o grupo protegido mantém a mesa; o desprotegido não.
 *
 * O trecho "login e volta ao destino" do fluxo A é do frontend (ProtectedRoute +
 * lib/redirectAposLogin, coberto em EntrarNaFila.test.tsx); aqui se prova que a
 * API devolve o destino certo e aceita a entrada com o token obtido depois.
 */
class FluxosFilaIntegracaoTest extends TestCase
{
    use RefreshDatabase;

    private Restaurante $restaurante;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'fila.desperdicio_maximo_lugares' => 2,
            'fila.espera_protegida_minutos' => 30,
        ]);

        $this->restaurante = Restaurante::factory()->comFilaAtiva()->create([
            'horario_abertura' => '00:00',
            'horario_fechamento' => '23:59',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ─── Fluxo A — QR ────────────────────────────────────────

    public function test_fluxo_a_do_qr_ate_a_entrada_na_fila(): void
    {
        Mesa::factory()->for($this->restaurante)->create(['capacidade' => 4, 'status' => 'ocupada', 'numero' => 1]);

        // 1-2. O restaurante abre o painel e gera o QR.
        $url = $this->comoRestaurante()->getJson('/api/restaurante/fila/qr')->assertOk()->json('url');

        // 3-4. O cliente abre a URL: o sistema identifica o restaurante e manda ao destino.
        $redirect = $this->get($url)->assertRedirect()->headers->get('Location');
        $this->assertMatchesRegularExpression(
            '#/app/restaurants/'.preg_quote($this->restaurante->id, '#').'/entrar-na-fila$#',
            $redirect
        );

        // 5. A rota do destino exige cliente: sem token, o login é inevitável.
        $this->getJson('/api/fila/posicao')->assertUnauthorized();

        $cliente = Cliente::factory()->create();
        $token = $this->postJson('/api/cliente/login', [
            'email' => $cliente->email,
            'password' => 'senha-de-teste',
        ])->assertOk()->json('token');
        $this->assertNotEmpty($token);

        // 6-7. Com a sessão, o cliente informa o grupo e entra na fila do restaurante certo.
        $restauranteId = basename(dirname($redirect));
        $this->assertSame($this->restaurante->id, $restauranteId);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/fila', ['restaurante_id' => $restauranteId, 'qntd_pessoas' => 3])
            ->assertCreated()
            ->assertJsonPath('data.posicao', 1)
            ->assertJsonPath('data.qntd_pessoas', 3)
            ->assertJsonPath('data.protegida', false);

        // Usuário com entrada ativa: a segunda tentativa (QR lido de novo) é recusada.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/fila', ['restaurante_id' => $restauranteId, 'qntd_pessoas' => 3])
            ->assertStatus(422);

        $this->assertSame(1, ClienteFila::ativas()->count());
    }

    // ─── Fluxo B — encaixe ───────────────────────────────────

    public function test_fluxo_b_encaixe_pelo_painel_e_pela_tela_do_cliente(): void
    {
        Carbon::setTestNow(now()->startOfHour()->addMinutes(10));

        // Existe uma mesa ocupada: a fila aceita entradas.
        Mesa::factory()->for($this->restaurante)->create(['capacidade' => 10, 'status' => 'ocupada', 'numero' => 9]);

        $casal = Cliente::factory()->create();
        $grupo = Cliente::factory()->create();

        // 1. Dois grupos entram: o casal primeiro, o grupo de 8 depois.
        $this->comoCliente($casal)->postJson('/api/fila', ['restaurante_id' => $this->restaurante->id, 'qntd_pessoas' => 2])->assertCreated();
        Carbon::setTestNow(now()->addMinutes(2));
        $this->comoCliente($grupo)->postJson('/api/fila', ['restaurante_id' => $this->restaurante->id, 'qntd_pessoas' => 8])
            ->assertCreated()
            ->assertJsonPath('data.posicao', 2);

        // 2-3. O painel cria uma mesa de 10 (livre): é a promoção que roda a priorização.
        $this->comoRestaurante()->postJson('/api/restaurante/mesas', ['numero' => 1, 'capacidade' => 10])->assertCreated();

        // 5. A alocação foi a do encaixe: o grupo de 8 ficou com a mesa de 10...
        $reserva = ClienteMesa::where('cliente_id', $grupo->id)->first();
        $this->assertNotNull($reserva, 'O grupo de 8 deveria ter sido chamado.');
        $this->assertSame(10, (int) Mesa::find($reserva->mesa_id)->capacidade);
        $this->assertSame(0, ClienteMesa::where('cliente_id', $casal->id)->count());

        // 4. ...e o que cada cliente vê é coerente. O grupo foi chamado:
        $this->comoCliente($grupo)->getJson($this->consulta())
            ->assertNotFound()
            ->assertJsonPath('status_saida', ClienteFila::STATUS_SAIDA_ATENDIDO);

        // O casal segue esperando, é o 1º da fila, ainda não é protegido e sabe até quando.
        $this->comoCliente($casal)->getJson($this->consulta())
            ->assertOk()
            ->assertJsonPath('posicao', 1)
            ->assertJsonPath('protegida', false)
            ->assertJsonPath('protecao_em_minutos', 28);

        // Dois minutos depois vaga a mesa de 4: o casal é atendido.
        $this->comoRestaurante()->postJson('/api/restaurante/mesas', ['numero' => 2, 'capacidade' => 4])->assertCreated();

        $this->assertSame(1, ClienteMesa::where('cliente_id', $casal->id)->count());
        $this->assertSame(0, ClienteFila::ativas()->count());
    }

    // ─── Fluxo C — anti-starvation ───────────────────────────

    public function test_fluxo_c_grupo_protegido_nao_e_ultrapassado_por_quem_chega_depois(): void
    {
        config(['fila.desperdicio_maximo_lugares' => 0]);   // só encaixe exato dispensa disputa

        Mesa::factory()->for($this->restaurante)->create(['capacidade' => 6, 'status' => 'ocupada', 'numero' => 9]);

        // 1. O grupo de 5 já esperou além do limite protegido.
        $protegido = $this->entradaAntiga(5, 40);

        // 2. Outros grupos entram depois, pela API — um deles com encaixe exato na mesa de 6.
        $exato = Cliente::factory()->create();
        $this->comoCliente($exato)->postJson('/api/fila', ['restaurante_id' => $this->restaurante->id, 'qntd_pessoas' => 6])
            ->assertCreated()
            ->assertJsonPath('data.posicao', 2);

        $this->comoCliente($protegido->cliente)->getJson($this->consulta())
            ->assertOk()
            ->assertJsonPath('posicao', 1)
            ->assertJsonPath('protegida', true)
            ->assertJsonPath('protecao_em_minutos', 0);

        // 3-5. Vaga uma mesa de 6: o protegido recebe, apesar de o outro encaixar melhor.
        $this->comoRestaurante()->postJson('/api/restaurante/mesas', ['numero' => 1, 'capacidade' => 6])->assertCreated();

        $this->assertSame(1, ClienteMesa::where('cliente_id', $protegido->cliente_id)->count());
        $this->assertSame(0, ClienteMesa::where('cliente_id', $exato->id)->count());
    }

    public function test_fluxo_c_controle_sem_protecao_o_encaixe_exato_passa_na_frente(): void
    {
        config(['fila.desperdicio_maximo_lugares' => 0]);

        Mesa::factory()->for($this->restaurante)->create(['capacidade' => 6, 'status' => 'ocupada', 'numero' => 9]);

        $jovem = $this->entradaAntiga(5, 5);   // ainda não protegido
        $exato = Cliente::factory()->create();
        $this->comoCliente($exato)->postJson('/api/fila', ['restaurante_id' => $this->restaurante->id, 'qntd_pessoas' => 6])->assertCreated();

        $this->comoRestaurante()->postJson('/api/restaurante/mesas', ['numero' => 1, 'capacidade' => 6])->assertCreated();

        $this->assertSame(1, ClienteMesa::where('cliente_id', $exato->id)->count());
        $this->assertSame(0, ClienteMesa::where('cliente_id', $jovem->cliente_id)->count());
    }

    // ─── Apoio ───────────────────────────────────────────────

    private function entradaAntiga(int $pessoas, int $haMinutos): ClienteFila
    {
        $fila = Fila::firstOrCreate(
            ['restaurante_id' => $this->restaurante->id, 'horario_reserva' => Fila::janelaAtual(), 'status' => Fila::STATUS_ABERTA]
        );

        return ClienteFila::factory()->for($fila)->entrouHa($haMinutos)->create(['qntd_pessoas' => $pessoas]);
    }

    private function consulta(): string
    {
        return '/api/fila/posicao?'.http_build_query([
            'restaurante_id' => $this->restaurante->id,
            'horario_reserva' => Fila::janelaAtual()->toIso8601String(),
        ]);
    }

    private function comoRestaurante(): static
    {
        return $this->withHeader('Authorization', 'Bearer '.auth('restaurante')->login($this->restaurante));
    }

    private function comoCliente(Cliente $cliente): static
    {
        return $this->withHeader('Authorization', 'Bearer '.auth('api')->login($cliente));
    }
}
