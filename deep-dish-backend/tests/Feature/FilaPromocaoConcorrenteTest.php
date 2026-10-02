<?php

namespace Tests\Feature;

use App\Models\ClienteFila;
use App\Models\ClienteMesa;
use App\Models\Fila;
use App\Models\Mesa;
use App\Models\Restaurante;
use App\Services\FilaService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Duas promoções simultâneas não podem dar a mesma mesa a dois clientes
 * (critério de aceite da #169).
 *
 * ── Por que não RefreshDatabase ──
 * Ele embrulha cada teste numa transação, e dado não confirmado é invisível para
 * outra conexão. Uma disputa de lock encenada ali não existiria: a segunda
 * conexão nem enxergaria o restaurante. DatabaseMigrations confirma tudo, ao
 * custo de remigrar a cada teste.
 *
 * ── Por que duas conexões, e não dois processos ──
 * pcntl_fork não existe no Windows, onde o time desenvolve. Duas conexões no
 * mesmo processo reproduzem a disputa que importa: o lock do Postgres é por
 * sessão, então a segunda sessão bloqueia de verdade na linha travada pela
 * primeira. O lock_timeout transforma esse bloqueio em exceção observável — sem
 * ele o teste ficaria pendurado até o Postgres desistir.
 *
 * A conexão extra é CLONADA da conexão padrão de propósito. O guard de
 * Tests\TestCase só valida 'database.default'; escrever host e database na mão
 * aqui abriria uma porta lateral para apontar a suíte para o Supabase.
 */
class FilaPromocaoConcorrenteTest extends TestCase
{
    use DatabaseTruncation;

    private const CONEXAO_RIVAL = 'rival';

    protected function setUp(): void
    {
        parent::setUp();

        $padrao = config('database.default');

        config([
            'database.connections.'.self::CONEXAO_RIVAL => config("database.connections.{$padrao}"),
        ]);

        DB::purge(self::CONEXAO_RIVAL);
    }

    /**
     * O truncate no fim não é zelo: sem ele, as linhas confirmadas por este teste
     * sobrevivem para as classes seguintes. O RefreshDatabase delas só desfaz a
     * própria transação e não remigra (o estado global já está marcado como
     * migrado), então um restaurante criado aqui aparecia, por exemplo, no
     * Restaurante::all() do RestauranteImagemTest e quebrava um teste alheio.
     */
    protected function tearDown(): void
    {
        DB::disconnect(self::CONEXAO_RIVAL);

        $this->truncateDatabaseTables();

        parent::tearDown();
    }

    public function test_promocao_concorrente_espera_o_lock_em_vez_de_alocar_a_mesma_mesa(): void
    {
        $restaurante = Restaurante::factory()->comFilaAtiva()->create();
        $fila = Fila::factory()->for($restaurante)->create();

        // Dois grupos que cabem na única mesa: sem o lock, as duas promoções
        // concorrentes escolheriam essa mesma mesa.
        ClienteFila::factory()->for($fila)->entrouHa(20)->create(['qntd_pessoas' => 2]);
        ClienteFila::factory()->for($fila)->entrouHa(10)->create(['qntd_pessoas' => 2]);

        $mesa = Mesa::factory()->for($restaurante)->comCapacidade(4)->create();

        // Sessão rival segura a linha do restaurante, como uma promoção em curso.
        $rival = DB::connection(self::CONEXAO_RIVAL);
        $rival->beginTransaction();
        $rival->select('SELECT id FROM restaurante WHERE id = ? FOR UPDATE', [$restaurante->id]);

        DB::statement("SET lock_timeout = '500ms'");

        try {
            app(FilaService::class)->processarPromocoes($restaurante->id);
            $this->fail('processarPromocoes() passou pela linha travada — o lockForUpdate não está segurando.');
        } catch (QueryException $e) {
            // 55P03 = lock_not_available. Esperar é o comportamento correto;
            // o timeout é só o que torna a espera observável no teste.
            $this->assertSame('55P03', (string) $e->getCode(), 'Esperado timeout de lock, veio outro erro: '.$e->getMessage());
        }

        // Ninguém foi alocado enquanto a linha estava travada.
        $this->assertSame(0, ClienteMesa::where('mesa_id', $mesa->id)->count());

        $rival->rollBack();
        DB::statement("SET lock_timeout = '0'");

        // Liberada a linha, a promoção acontece — e a mesa vai para uma pessoa só.
        $promovidos = app(FilaService::class)->processarPromocoes($restaurante->id);

        $this->assertCount(1, $promovidos);
        $this->assertSame(1, ClienteMesa::where('mesa_id', $mesa->id)->count());
    }
}
