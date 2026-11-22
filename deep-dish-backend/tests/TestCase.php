<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Camada 1 — trechos de host que identificam o banco Supabase compartilhado
     * do time (sem staging, sem backup). Bate aqui e aborta, não importa o que
     * a camada 2 diga. Isto NUNCA vira uma allowlist: é sempre "contém supabase
     * => aborta", de propósito.
     */
    private const HOSTS_PROIBIDOS = [
        'supabase',
    ];

    /**
     * Camada 2 — allowlist de hosts de teste descartáveis (nega por padrão).
     * Fora de sqlite ':memory:', só passa se o host bater EXATAMENTE com um
     * destes. Um host novo e legítimo (ex.: outro serviço de banco de teste no
     * docker-compose.yml) precisa ser adicionado aqui deliberadamente — esta
     * lista não deve nunca virar "qualquer coisa que não contenha supabase".
     */
    private const HOSTS_SEGUROS = [
        '127.0.0.1',
        'localhost',
        'postgres_test', // serviço de banco de teste em docker-compose.yml
    ];

    /**
     * Camada 3 — o nome do banco precisa terminar com isto. O container
     * postgres_test guarda também o 'deepdish_dev' (desenvolvimento), no mesmo
     * host da allowlist. Rodando a suíte de dentro de um container, o DB_* do
     * .env vence o phpunit.xml (que não usa force="true") e a suíte cairia no
     * banco de dev — só a camada 2 deixaria o RefreshDatabase apagá-lo.
     */
    private const SUFIXO_BANCO_TESTE = '_test';

    /**
     * createApplication() é chamado por refreshApplication(), que roda ANTES
     * de setUpTraits() (é isso que dispara RefreshDatabase). Checar aqui, e
     * não em setUp(), é o que garante que a trava existe antes de qualquer
     * possibilidade de drop/migrate no banco.
     *
     * @return Application
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $this->abortarSeBancoInseguro($app);

        return $app;
    }

    private function abortarSeBancoInseguro(Application $app): void
    {
        $connectionName = $app['config']->get('database.default');
        $config = $app['config']->get("database.connections.{$connectionName}", []);
        $host = $config['host'] ?? null;
        $database = $config['database'] ?? null;

        // Camada 1: nunca, em nenhuma hipótese, tocar produção — isto roda
        // sempre, mesmo que o host também esteja (por engano) em HOSTS_SEGUROS.
        foreach (self::HOSTS_PROIBIDOS as $proibido) {
            if ($host !== null && stripos($host, $proibido) !== false) {
                throw new RuntimeException(
                    "TESTES ABORTADOS: a conexão '{$connectionName}' aponta para host "
                    ."'{$host}', que parece ser o banco Supabase compartilhado do time "
                    .'(sem staging, sem backup). Rodar a suíte aqui (ex.: com RefreshDatabase) '
                    .'apagaria o banco de todo mundo.'
                );
            }
        }

        // Camada 2: sqlite in-memory não tem host — é sempre seguro por
        // construção (cada teste recebe um banco novo e descartável).
        if ($connectionName === 'sqlite' && $database === ':memory:') {
            return;
        }

        // Fora disso, deny-by-default: o host precisa estar EXPLICITAMENTE na
        // allowlist. Não confiamos em "não contém supabase" — confiamos só na
        // lista.
        $hostSeguro = $host !== null
            && in_array(strtolower($host), array_map('strtolower', self::HOSTS_SEGUROS), true);

        if (! $hostSeguro) {
            throw new RuntimeException(
                "TESTES ABORTADOS: conexão de teste é '{$connectionName}' com host "
                .($host !== null ? "'{$host}'" : '(nenhum)').' e database '
                .($database !== null ? "'{$database}'" : '(nenhum)').', fora da allowlist '
                .'de bancos de teste seguros.'."\n"
                .'Permitido: sqlite \':memory:\', ou host em ['.implode(', ', self::HOSTS_SEGUROS)."].\n"
                .'Se este é um host de teste novo e legítimo (ex.: novo serviço de banco '
                .'no docker-compose.yml), adicione-o a HOSTS_SEGUROS em tests/TestCase.php '
                .'deliberadamente — não amplie esta checagem para "qualquer coisa que não '
                .'seja supabase".'
            );
        }

        // Camada 3: host seguro não basta — o mesmo host tem o banco de dev.
        if ($database === null || ! str_ends_with(strtolower($database), self::SUFIXO_BANCO_TESTE)) {
            throw new RuntimeException(
                "TESTES ABORTADOS: a conexão '{$connectionName}' aponta para o database "
                .($database !== null ? "'{$database}'" : '(nenhum)').", que não termina em '"
                .self::SUFIXO_BANCO_TESTE."'. O RefreshDatabase apagaria esse banco.\n"
                .'Provável causa: a suíte rodou de dentro de um container, onde o DB_* do .env '
                .'vence o phpunit.xml. Rode os testes a partir do host: composer test'
            );
        }
    }
}
