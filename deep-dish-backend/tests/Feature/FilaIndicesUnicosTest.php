<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ClienteFila;
use App\Models\Fila;
use App\Models\Restaurante;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * As duas regras da fila garantidas pelo banco, e não só pela checagem em PHP.
 *
 * Estes testes escrevem direto pelos models, pulando o FilaService de
 * propósito: o que está sendo verificado é o índice, que é a última linha de
 * defesa quando duas requisições simultâneas passam pela mesma checagem.
 */
class FilaIndicesUnicosTest extends TestCase
{
    use RefreshDatabase;

    public function test_nao_existem_duas_filas_abertas_para_a_mesma_janela(): void
    {
        $restaurante = Restaurante::factory()->comFilaAtiva()->create();
        $janela = now()->utc()->startOfHour();

        Fila::factory()->for($restaurante)->create([
            'horario_reserva' => $janela,
            'status' => Fila::STATUS_ABERTA,
        ]);

        $this->expectException(QueryException::class);

        Fila::factory()->for($restaurante)->create([
            'horario_reserva' => $janela,
            'status' => Fila::STATUS_ABERTA,
        ]);
    }

    public function test_fila_encerrada_nao_atrapalha_a_proxima_da_mesma_janela(): void
    {
        $restaurante = Restaurante::factory()->comFilaAtiva()->create();
        $janela = now()->utc()->startOfHour();

        Fila::factory()->for($restaurante)->create([
            'horario_reserva' => $janela,
            'status' => Fila::STATUS_ENCERRADA,
        ]);

        $nova = Fila::factory()->for($restaurante)->create([
            'horario_reserva' => $janela,
            'status' => Fila::STATUS_ABERTA,
        ]);

        $this->assertModelExists($nova);
    }

    public function test_cliente_nao_tem_duas_entradas_ativas_na_mesma_fila(): void
    {
        $fila = Fila::factory()->create();
        $cliente = Cliente::factory()->create();

        ClienteFila::factory()->for($fila)->for($cliente, 'cliente')->create();

        $this->expectException(QueryException::class);

        ClienteFila::factory()->for($fila)->for($cliente, 'cliente')->create();
    }

    public function test_quem_saiu_pode_voltar_para_a_mesma_fila(): void
    {
        $fila = Fila::factory()->create();
        $cliente = Cliente::factory()->create();

        $primeira = ClienteFila::factory()->for($fila)->for($cliente, 'cliente')->create();
        $primeira->registrarSaida(ClienteFila::STATUS_SAIDA_DESISTIU);

        $segunda = ClienteFila::factory()->for($fila)->for($cliente, 'cliente')->create();

        $this->assertModelExists($segunda);
        $this->assertSame(
            ClienteFila::STATUS_SAIDA_DESISTIU,
            ClienteFila::withTrashed()->findOrFail($primeira->id)->status_saida,
            'A saída anterior precisa continuar no histórico.'
        );
    }
}
