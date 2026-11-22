<?php

namespace Tests\Unit;

use App\Models\ClienteFila;
use Tests\TestCase;

/**
 * Testes de unidade do ClienteFila — o model que guarda o histórico da fila.
 *
 * Nenhum destes testes toca o banco: todos exercitam caminhos que retornam
 * antes de qualquer consulta. Isso é proposital, para a suíte Unit continuar
 * rodando mesmo enquanto a infraestrutura de banco de teste não existe.
 */
class ClienteFilaTest extends TestCase
{
    public function test_constantes_de_status_saida_tem_os_valores_gravados_no_banco(): void
    {
        // Os valores viram string na coluna status_saida e são lidos pelo
        // Analytics do Sprint 2. Renomear em silêncio invalidaria o histórico.
        $this->assertSame('desistiu', ClienteFila::STATUS_SAIDA_DESISTIU);
        $this->assertSame('atendido', ClienteFila::STATUS_SAIDA_ATENDIDO);
        $this->assertSame('removido', ClienteFila::STATUS_SAIDA_REMOVIDO);
        $this->assertSame('expirado', ClienteFila::STATUS_SAIDA_EXPIRADO);
    }

    public function test_chamado_em_e_convertido_para_data(): void
    {
        // A #12 compara 'chamado_em' com a janela de tolerância para marcar o
        // no-show. Sem o cast a coluna volta do Postgres como string e a
        // comparação de datas passa a ser comparação de texto.
        $registro = new ClienteFila;

        $this->assertArrayHasKey('chamado_em', $registro->getCasts());
        $this->assertSame('datetime', $registro->getCasts()['chamado_em']);
    }

    public function test_quem_ja_saiu_da_fila_nao_tem_posicao(): void
    {
        $registro = new ClienteFila;
        $registro->status_saida = ClienteFila::STATUS_SAIDA_ATENDIDO;

        $this->assertNull($registro->posicao);
    }

    public function test_registrar_saida_e_idempotente(): void
    {
        $registro = new ClienteFila;
        $registro->status_saida = ClienteFila::STATUS_SAIDA_DESISTIU;
        $registro->saiu_em = now()->subHour();

        $saiuEmOriginal = $registro->saiu_em;

        // Segunda chamada não pode sobrescrever o histórico já gravado.
        $registro->registrarSaida(ClienteFila::STATUS_SAIDA_REMOVIDO);

        $this->assertSame(ClienteFila::STATUS_SAIDA_DESISTIU, $registro->status_saida);
        $this->assertTrue($saiuEmOriginal->equalTo($registro->saiu_em));
    }

    public function test_scope_ativas_filtra_por_status_saida_nulo(): void
    {
        $sql = strtolower(ClienteFila::query()->ativas()->toSql());

        $this->assertStringContainsString('status_saida', $sql);
        $this->assertStringContainsString('is null', $sql);
    }

    public function test_posicao_nao_esta_no_appends(): void
    {
        // Estava no $appends e disparava um COUNT por registro serializado.
        // O Sprint 3 vai transmitir a fila por broadcast, o que amplificaria
        // esse N+1 — por isso a ausência aqui é comportamento, não detalhe.
        $this->assertNotContains('posicao', (new ClienteFila)->getAppends());
    }
}
