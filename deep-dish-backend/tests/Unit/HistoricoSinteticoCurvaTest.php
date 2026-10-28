<?php

namespace Tests\Unit;

use Database\Seeders\HistoricoSinteticoSeeder;
use Tests\TestCase;

/**
 * Testes da curva de demanda do histórico sintético.
 *
 * Nenhum toca o banco: `pesoDoSlot` e `multiplicadorDoDia` são estáticas e puras
 * exatamente para que o critério de aceite "distribuição por hora do dia
 * reproduz os dois picos" possa ser verificado sem infraestrutura — a suíte
 * roda em SQLite, e as migrations do projeto usam SQL específico de Postgres.
 *
 * Os minutos são sempre horário de Brasília: é nesse fuso que os picos foram
 * especificados, e o seeder converte para UTC só na gravação.
 */
class HistoricoSinteticoCurvaTest extends TestCase
{
    private const PASSO = 30;

    /**
     * Série de pesos por slot ao longo do dia, do jeito que o seeder percorre.
     *
     * @return array<int, float> minutos desde a meia-noite => peso
     */
    private function serieDoDia(): array
    {
        $serie = [];

        for ($m = 0; $m < 24 * 60; $m += self::PASSO) {
            $serie[$m] = HistoricoSinteticoSeeder::pesoDoSlot($m);
        }

        return $serie;
    }

    /**
     * @return array<int, float> os máximos locais: minuto => peso
     */
    private function picos(): array
    {
        $serie = $this->serieDoDia();
        $minutos = array_keys($serie);
        $picos = [];

        for ($i = 1; $i < count($minutos) - 1; $i++) {
            $anterior = $serie[$minutos[$i - 1]];
            $atual = $serie[$minutos[$i]];
            $proximo = $serie[$minutos[$i + 1]];

            if ($atual > $anterior && $atual > $proximo) {
                $picos[$minutos[$i]] = $atual;
            }
        }

        return $picos;
    }

    public function test_a_curva_tem_exatamente_dois_picos(): void
    {
        $picos = $this->picos();

        $this->assertCount(
            2,
            $picos,
            'A curva deveria ter só o pico de almoço e o de jantar. Encontrados: '
            .implode(', ', array_map(
                fn (int $m) => sprintf('%02d:%02d', intdiv($m, 60), $m % 60),
                array_keys($picos)
            ))
        );
    }

    public function test_os_picos_caem_nas_janelas_de_almoco_e_jantar(): void
    {
        $minutos = array_keys($this->picos());
        sort($minutos);

        [$almoco, $jantar] = $minutos;

        // 11h30-14h
        $this->assertGreaterThanOrEqual(11 * 60 + 30, $almoco);
        $this->assertLessThanOrEqual(14 * 60, $almoco);

        // 19h-22h
        $this->assertGreaterThanOrEqual(19 * 60, $jantar);
        $this->assertLessThanOrEqual(22 * 60, $jantar);
    }

    public function test_o_jantar_e_mais_intenso_que_o_almoco(): void
    {
        $picos = $this->picos();
        ksort($picos);

        [$almoco, $jantar] = array_values($picos);

        $this->assertGreaterThan($almoco, $jantar);
    }

    /**
     * Sem um vale claro entre os picos, o gráfico viraria um platô e os dois
     * picos deixariam de ser legíveis.
     */
    public function test_o_vale_da_tarde_e_bem_menor_que_os_picos(): void
    {
        $vale = HistoricoSinteticoSeeder::pesoDoSlot(16 * 60 + 30);
        $menorPico = min($this->picos());

        $this->assertLessThan($menorPico / 3, $vale);
    }

    public function test_a_madrugada_fica_no_piso(): void
    {
        $this->assertEqualsWithDelta(0.12, HistoricoSinteticoSeeder::pesoDoSlot(4 * 60), 0.001);
    }

    public function test_fim_de_semana_e_mais_cheio_que_dia_util(): void
    {
        $sabado = HistoricoSinteticoSeeder::multiplicadorDoDia(6);
        $domingo = HistoricoSinteticoSeeder::multiplicadorDoDia(0);

        // Segunda a quinta
        foreach ([1, 2, 3, 4] as $diaUtil) {
            $peso = HistoricoSinteticoSeeder::multiplicadorDoDia($diaUtil);

            $this->assertGreaterThan($peso, $sabado, "sábado deveria superar o dia {$diaUtil}");
            $this->assertGreaterThan($peso, $domingo, "domingo deveria superar o dia {$diaUtil}");
        }
    }

    public function test_sabado_e_o_dia_mais_cheio_da_semana(): void
    {
        $pesos = array_map(
            fn (int $dia) => HistoricoSinteticoSeeder::multiplicadorDoDia($dia),
            range(0, 6)
        );

        $this->assertSame(6, array_search(max($pesos), $pesos, true));
    }
}
