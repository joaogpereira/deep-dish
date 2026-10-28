<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Em dev, o link de verificação também vai para o log: dá para ativar qualquer
 * conta de teste sem abrir a caixa de e-mail. Em produção não pode ir — é um
 * link de autenticação válido por 24 horas, e log não é lugar de credencial.
 */
class VerificacaoEmailLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_fora_de_producao_o_link_vai_para_o_log(): void
    {
        Log::spy();
        $cliente = Cliente::factory()->create();

        (new VerifyEmailNotification('cliente'))->toMail($cliente);

        Log::shouldHaveReceived('info')->once()->withArgs(
            fn (string $mensagem, array $contexto) => str_contains($contexto['url'], 'signature=')
                && $contexto['email'] === $cliente->email
        );
    }

    public function test_em_producao_o_link_nao_vai_para_o_log(): void
    {
        Log::spy();
        $this->app->detectEnvironment(fn () => 'production');
        $cliente = Cliente::factory()->create();

        (new VerifyEmailNotification('cliente'))->toMail($cliente);

        Log::shouldNotHaveReceived('info');
    }
}
