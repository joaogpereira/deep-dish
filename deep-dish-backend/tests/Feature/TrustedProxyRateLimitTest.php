<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De quem é o IP que o rate limiter conta.
 *
 * O limite das rotas de entrada é por IP, então quem decide o IP decide o
 * limite. Sem proxy configurado o Laravel usa o IP da conexão e ignora o
 * X-Forwarded-For — é o que protege contra alguém forjar o cabeçalho para
 * ganhar um balde novo a cada requisição. Com proxy configurado ele passa a
 * confiar no cabeçalho, que é o necessário para não jogar todo mundo no balde
 * do proxy.
 *
 * Os dois lados estão aqui porque errar para qualquer um dos lados quebra algo:
 * confiar de menos gera 429 em usuário legítimo, confiar de mais apaga o limite.
 */
class TrustedProxyRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const LOGIN = '/api/cliente/login';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['cache']->store()->flush();
    }

    public function test_sem_proxy_configurado_o_x_forwarded_for_nao_cria_balde_novo(): void
    {
        $credenciais = ['email' => 'alvo@deepdish.test', 'password' => 'errada'];

        // Cada tentativa alega vir de um IP diferente. Como nenhum proxy é
        // confiável, o Laravel descarta a alegação e conta tudo no IP da conexão.
        for ($i = 1; $i <= 5; $i++) {
            $this->withHeader('X-Forwarded-For', "203.0.113.{$i}")
                ->postJson(self::LOGIN, $credenciais)
                ->assertStatus(401);
        }

        $this->withHeader('X-Forwarded-For', '203.0.113.99')
            ->postJson(self::LOGIN, $credenciais)
            ->assertStatus(429);
    }

    public function test_com_proxy_configurado_o_ip_real_do_cabecalho_separa_os_baldes(): void
    {
        // O teste chega como 127.0.0.1; declarar esse IP como proxy é o
        // equivalente local a apontar para o nginx/PaaS em produção.
        config(['trustedproxy.proxies' => '127.0.0.1']);

        $credenciais = ['email' => 'alvo@deepdish.test', 'password' => 'errada'];

        // Um cliente atrás do proxy esgota o limite dele...
        for ($i = 1; $i <= 5; $i++) {
            $this->withHeader('X-Forwarded-For', '203.0.113.10')
                ->postJson(self::LOGIN, $credenciais)
                ->assertStatus(401);
        }

        $this->withHeader('X-Forwarded-For', '203.0.113.10')
            ->postJson(self::LOGIN, $credenciais)
            ->assertStatus(429);

        // ...e o vizinho, que só divide o proxy com ele, continua atendido.
        $this->withHeader('X-Forwarded-For', '203.0.113.20')
            ->postJson(self::LOGIN, $credenciais)
            ->assertStatus(401);
    }
}
