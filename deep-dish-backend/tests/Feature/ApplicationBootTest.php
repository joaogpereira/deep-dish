<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Teste de fumaça: garante que a aplicação inicializa.
 *
 * Não valida regra de negócio — pega falha de boot, que é o tipo de erro que
 * derruba tudo e costuma vir de configuração ausente (APP_KEY, JWT_SECRET) ou
 * de service provider quebrado. Não depende de banco.
 */
class ApplicationBootTest extends TestCase
{
    public function test_a_aplicacao_inicializa(): void
    {
        $this->assertTrue($this->app->isBooted());
    }

    public function test_o_guard_de_cliente_e_o_de_restaurante_estao_configurados(): void
    {
        $guards = config('auth.guards');

        $this->assertArrayHasKey('api', $guards);
        $this->assertArrayHasKey('restaurante', $guards);
        $this->assertSame('jwt', $guards['api']['driver']);
        $this->assertSame('jwt', $guards['restaurante']['driver']);
    }

    public function test_rotas_da_api_estao_registradas(): void
    {
        $rotas = collect(app('router')->getRoutes())
            ->map(fn ($rota) => $rota->uri())
            ->filter(fn (string $uri) => str_starts_with($uri, 'api/'));

        $this->assertNotEmpty($rotas, 'Nenhuma rota de API registrada.');
    }
}
