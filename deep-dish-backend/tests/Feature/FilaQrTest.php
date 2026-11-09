<?php

namespace Tests\Feature;

use App\Http\Controllers\FilaQrController;
use App\Models\Cliente;
use App\Models\Restaurante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QR de entrada na fila (issue #174): URL assinada e permanente por restaurante,
 * painel que a entrega só ao dono, e um redirecionamento que sempre termina no
 * frontend — com o motivo, quando a entrada não é possível.
 *
 * Login e retorno ao destino são do frontend (ProtectedRoute + lib de
 * redirecionamento); aqui se garante que o backend aponta para a rota protegida
 * certa, que é o que dispara essa volta.
 */
class FilaQrTest extends TestCase
{
    use RefreshDatabase;

    private function frontend(): string
    {
        return rtrim((string) config('app.frontend_url'), '/');
    }

    private function comoRestaurante(Restaurante $restaurante): static
    {
        return $this->withHeader('Authorization', 'Bearer '.auth('restaurante')->login($restaurante));
    }

    private function destino(Restaurante $restaurante): string
    {
        return "{$this->frontend()}/app/restaurants/{$restaurante->id}/entrar-na-fila";
    }

    // ─── Geração ─────────────────────────────────────────────

    public function test_painel_devolve_url_assinada_do_proprio_restaurante(): void
    {
        $restaurante = Restaurante::factory()->comFilaAtiva()->create();

        $resposta = $this->comoRestaurante($restaurante)->getJson('/api/restaurante/fila/qr');

        $resposta->assertOk()
            ->assertJsonPath('restaurante_id', $restaurante->id)
            ->assertJsonPath('nome', $restaurante->name);

        $url = $resposta->json('url');
        $this->assertStringContainsString("/fila/qr/{$restaurante->id}", $url);
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_url_do_qr_nao_expira_e_nao_expoe_dado_sensivel(): void
    {
        $restaurante = Restaurante::factory()->comFilaAtiva()->create();

        $url = $this->comoRestaurante($restaurante)->getJson('/api/restaurante/fila/qr')->json('url');

        $this->assertStringNotContainsString('expires=', $url);
        $this->assertStringNotContainsString($restaurante->email, urldecode($url));
        $this->assertStringNotContainsString((string) $restaurante->cnpj, $url);
    }

    public function test_url_e_estavel_entre_chamadas(): void
    {
        $restaurante = Restaurante::factory()->comFilaAtiva()->create();

        $a = $this->comoRestaurante($restaurante)->getJson('/api/restaurante/fila/qr')->json('url');
        $this->travel(30)->days();
        $b = $this->comoRestaurante($restaurante)->getJson('/api/restaurante/fila/qr')->json('url');

        $this->assertSame($a, $b);
    }

    public function test_cada_restaurante_recebe_o_proprio_qr(): void
    {
        $um = Restaurante::factory()->comFilaAtiva()->create();
        $outro = Restaurante::factory()->comFilaAtiva()->create();

        $urlUm = $this->comoRestaurante($um)->getJson('/api/restaurante/fila/qr')->json('url');
        $urlOutro = $this->comoRestaurante($outro)->getJson('/api/restaurante/fila/qr')->json('url');

        $this->assertStringContainsString($um->id, $urlUm);
        $this->assertStringNotContainsString($outro->id, $urlUm);
        $this->assertNotSame($urlUm, $urlOutro);
    }

    // ─── Autorização do painel ───────────────────────────────

    public function test_painel_do_qr_exige_autenticacao(): void
    {
        $this->getJson('/api/restaurante/fila/qr')->assertUnauthorized();
    }

    public function test_cliente_nao_acessa_o_painel_do_qr(): void
    {
        $token = auth('api')->login(Cliente::factory()->create());

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/restaurante/fila/qr')
            ->assertStatus(401);
    }

    public function test_painel_nao_aceita_escolher_outro_restaurante(): void
    {
        $um = Restaurante::factory()->comFilaAtiva()->create();
        $outro = Restaurante::factory()->comFilaAtiva()->create();

        $url = $this->comoRestaurante($um)
            ->getJson('/api/restaurante/fila/qr?restaurante_id='.$outro->id)
            ->json('url');

        $this->assertStringContainsString($um->id, $url);
        $this->assertStringNotContainsString($outro->id, $url);
    }

    // ─── Leitura do QR ───────────────────────────────────────

    public function test_qr_valido_redireciona_para_a_entrada_na_fila_do_restaurante(): void
    {
        $restaurante = Restaurante::factory()->comFilaAtiva()->create();

        $this->get(FilaQrController::urlAssinada($restaurante->id))
            ->assertRedirect($this->destino($restaurante));
    }

    public function test_qr_continua_valido_muito_tempo_depois(): void
    {
        $restaurante = Restaurante::factory()->comFilaAtiva()->create();
        $url = FilaQrController::urlAssinada($restaurante->id);

        $this->travel(2)->years();

        $this->get($url)->assertRedirect($this->destino($restaurante));
    }

    public function test_leitura_do_qr_nao_exige_autenticacao_no_backend(): void
    {
        $restaurante = Restaurante::factory()->comFilaAtiva()->create();

        // Sem Authorization: a barreira de login é a rota protegida do frontend.
        $resposta = $this->get(FilaQrController::urlAssinada($restaurante->id));

        $resposta->assertStatus(302);
        $this->assertStringContainsString('/app/restaurants/', $resposta->headers->get('Location'));
    }

    // ─── Produção: domínios, proxy e rotação de chave ────────

    public function test_url_usa_a_base_publica_configurada_e_nao_o_host_da_requisicao(): void
    {
        config(['app.url' => 'https://api.deepdish.example']);
        $restaurante = Restaurante::factory()->comFilaAtiva()->create();

        // O painel pode alcançar a API por um host interno ou por outro domínio.
        $url = $this->comoRestaurante($restaurante)
            ->getJson('http://interno.local/api/restaurante/fila/qr')
            ->json('url');

        $this->assertStringStartsWith("https://api.deepdish.example/api/fila/qr/{$restaurante->id}?signature=", $url);
    }

    public function test_assinatura_vale_em_qualquer_host_e_esquema(): void
    {
        config(['app.url' => 'https://api.deepdish.example']);
        $restaurante = Restaurante::factory()->comFilaAtiva()->create();
        $url = FilaQrController::urlAssinada($restaurante->id);

        // Atrás de proxy/CDN a requisição chega com host e esquema diferentes dos
        // públicos; a assinatura relativa não pode depender deles.
        $caminho = substr($url, strlen('https://api.deepdish.example'));

        foreach (['http://localhost', 'http://10.0.0.5:8000', 'https://outro-dominio.example'] as $origem) {
            $this->get($origem.$caminho)->assertRedirect($this->destino($restaurante));
        }
    }

    public function test_redirect_vai_para_o_dominio_do_frontend_configurado(): void
    {
        config(['app.frontend_url' => 'https://app.deepdish.example/']);
        $restaurante = Restaurante::factory()->comFilaAtiva()->create();

        $this->get(FilaQrController::urlAssinada($restaurante->id))
            ->assertRedirect("https://app.deepdish.example/app/restaurants/{$restaurante->id}/entrar-na-fila");
    }

    public function test_rotacao_da_app_key_invalida_o_qr_impresso(): void
    {
        $restaurante = Restaurante::factory()->comFilaAtiva()->create();
        $url = FilaQrController::urlAssinada($restaurante->id);

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        $this->get($url)->assertRedirect("{$this->frontend()}/qr-erro?motivo=assinatura_invalida");
    }

    public function test_chave_antiga_em_previous_keys_mantem_o_qr_impresso_valido(): void
    {
        $restaurante = Restaurante::factory()->comFilaAtiva()->create();
        $url = FilaQrController::urlAssinada($restaurante->id);
        $antiga = config('app.key');

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.previous_keys' => [$antiga],
        ]);

        $this->get($url)->assertRedirect($this->destino($restaurante));
    }

    // ─── Rejeições ───────────────────────────────────────────

    public function test_url_sem_assinatura_e_rejeitada(): void
    {
        $restaurante = Restaurante::factory()->comFilaAtiva()->create();

        $this->get("/api/fila/qr/{$restaurante->id}")
            ->assertRedirect("{$this->frontend()}/qr-erro?motivo=assinatura_invalida");
    }

    public function test_assinatura_adulterada_e_rejeitada(): void
    {
        $restaurante = Restaurante::factory()->comFilaAtiva()->create();
        $url = FilaQrController::urlAssinada($restaurante->id);

        $this->get(substr($url, 0, -4).'0000')
            ->assertRedirect("{$this->frontend()}/qr-erro?motivo=assinatura_invalida");
    }

    public function test_trocar_o_id_invalida_a_assinatura(): void
    {
        $meu = Restaurante::factory()->comFilaAtiva()->create();
        $alheio = Restaurante::factory()->comFilaAtiva()->create();

        $url = str_replace($meu->id, $alheio->id, FilaQrController::urlAssinada($meu->id));

        $this->get($url)
            ->assertRedirect("{$this->frontend()}/qr-erro?motivo=assinatura_invalida");
    }

    public function test_parametro_extra_invalida_a_assinatura(): void
    {
        $restaurante = Restaurante::factory()->comFilaAtiva()->create();
        $url = FilaQrController::urlAssinada($restaurante->id);

        $this->get($url.'&destino=https://evil.example')
            ->assertRedirect("{$this->frontend()}/qr-erro?motivo=assinatura_invalida");
    }

    public function test_restaurante_inexistente(): void
    {
        $url = FilaQrController::urlAssinada('11111111-1111-4111-8111-111111111111');

        $this->get($url)
            ->assertRedirect("{$this->frontend()}/qr-erro?motivo=restaurante_inexistente");
    }

    public function test_restaurante_com_fila_fechada(): void
    {
        $restaurante = Restaurante::factory()->create(['fila_ativa' => false]);

        $this->get(FilaQrController::urlAssinada($restaurante->id))
            ->assertRedirect("{$this->frontend()}/qr-erro?motivo=fila_fechada&restaurante={$restaurante->id}");
    }

    public function test_id_fora_do_formato_uuid_nem_chega_ao_controller(): void
    {
        $this->get('/api/fila/qr/nao-e-uuid')->assertNotFound();
    }
}
