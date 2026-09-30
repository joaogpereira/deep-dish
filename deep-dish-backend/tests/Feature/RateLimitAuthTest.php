<?php

namespace Tests\Feature;

use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * As portas de entrada da API têm limite de requisições.
 *
 * No Laravel 11 o grupo 'api' não vem com throttle; sem o throttleApi() no
 * bootstrap e o throttle:5,1 na rota, dava para tentar senha sem parar. Os
 * testes provam o corte na 6ª tentativa do mesmo minuto, nos dois endpoints que
 * dão acesso a uma conta: o login (chute de senha) e o reset-password (chute do
 * token que veio por e-mail).
 */
class RateLimitAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // O contador do rate limiter vive no cache, que o RefreshDatabase não
        // toca; sem zerar, a ordem dos testes influenciaria o resultado.
        $this->app['cache']->store()->flush();
    }

    public function test_login_bloqueia_apos_cinco_tentativas_no_mesmo_minuto(): void
    {
        $credenciaisErradas = ['email' => 'bsales@example.net', 'password' => 'errada'];

        // throttle:5,1 → as cinco primeiras passam (401), a sexta é barrada (429).
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/cliente/login', $credenciaisErradas)->assertStatus(401);
        }

        $this->postJson('/api/cliente/login', $credenciaisErradas)->assertStatus(429);
    }

    public function test_limite_e_por_ip_entao_nao_vaza_se_a_senha_estiver_certa(): void
    {
        Cliente::factory()->create([
            'email' => 'certo@deepdish.test',
            'password' => 'senha-de-teste',
        ]);

        // Esgota o limite com tentativas erradas no mesmo IP...
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/cliente/login', ['email' => 'certo@deepdish.test', 'password' => 'errada']);
        }

        // ...a tentativa seguinte, mesmo com a senha certa, é barrada: o limite é
        // por IP, então força bruta não escapa acertando no fim.
        $this->postJson('/api/cliente/login', [
            'email' => 'certo@deepdish.test',
            'password' => 'senha-de-teste',
        ])->assertStatus(429);
    }

    /**
     * O reset-password é o endpoint que consome o token do e-mail: acertar um
     * token vale a conta inteira, sem passar pela senha. Ele ficou de fora do
     * primeiro passe de rate limit e respondia só ao teto global de 60/min.
     */
    public function test_reset_password_bloqueia_apos_cinco_chutes_de_token(): void
    {
        $chute = [
            'token' => 'token-invalido',
            'email' => 'alvo@deepdish.test',
            'password' => 'nova-senha',
            'password_confirmation' => 'nova-senha',
        ];

        // Token inválido responde 422; o que importa aqui é quantas vezes deixa tentar.
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/cliente/reset-password', $chute)->assertStatus(422);
        }

        $this->postJson('/api/cliente/reset-password', $chute)->assertStatus(429);
    }

    /** O mesmo vale para o lado do restaurante, que tem rota própria. */
    public function test_reset_password_do_restaurante_tambem_bloqueia(): void
    {
        $chute = [
            'token' => 'token-invalido',
            'email' => 'alvo@deepdish.test',
            'password' => 'nova-senha',
            'password_confirmation' => 'nova-senha',
        ];

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/restaurante/reset-password', $chute)->assertStatus(422);
        }

        $this->postJson('/api/restaurante/reset-password', $chute)->assertStatus(429);
    }
}
