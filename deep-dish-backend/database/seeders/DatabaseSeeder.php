<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\ClienteFila;
use App\Models\ClienteMesa;
use App\Models\Fila;
use App\Models\Funcionario;
use App\Models\Mesa;
use App\Models\Restaurante;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Cenario de desenvolvimento: dois restaurantes prontos para clicar.
 *
 * Nao e dado historico para analytics — isso e a issue #158, que vai usar estas
 * mesmas factories para gerar ~12 semanas com curvas realistas.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private const SENHA = 'senha-de-teste';

    public function run(): void
    {
        $clientes = Cliente::factory()->count(8)->create(['password' => self::SENHA]);

        $comFila = $this->restauranteComFila($clientes);
        $comReservas = $this->restauranteComReservas($clientes);

        $this->command->info('Cenario criado:');
        $this->command->line("  Fila virtual   {$comFila->email}");
        $this->command->line("  Reservas       {$comReservas->email}");
        $this->command->line('  Clientes       '.$clientes->first()->email.' (e mais 7)');
        $this->command->line('  Senha (todos)  '.self::SENHA);
    }

    /**
     * Restaurante com fila virtual ligada e gente esperando.
     */
    private function restauranteComFila($clientes): Restaurante
    {
        $restaurante = Restaurante::factory()->comFilaAtiva()->create([
            'name' => 'Cantina do Vale',
            'tipo' => 'Brasileira',
            'imagem_url' => $this->foto('cantina-do-vale.jpg'),
            'email' => 'fila@deepdish.test',
            'password' => self::SENHA,
        ]);

        Mesa::factory()->count(6)->for($restaurante)->create();
        Mesa::factory()->for($restaurante)->bloqueada()->create();
        Funcionario::factory()->count(3)->for($restaurante)->create();

        $fila = Fila::factory()->for($restaurante)->create();

        // Ordem da fila e derivada de created_at — o entrouHa() controla isso.
        foreach ([25, 18, 12, 5] as $i => $minutos) {
            ClienteFila::factory()
                ->for($fila)
                ->for($clientes[$i], 'cliente')
                ->entrouHa($minutos)
                ->create();
        }

        // Historico: quem ja passou por essa fila.
        ClienteFila::factory()->for($fila)->for($clientes[4], 'cliente')->atendido()->create();
        ClienteFila::factory()->for($fila)->for($clientes[5], 'cliente')->desistiu()->create();

        return $restaurante;
    }

    /**
     * Restaurante com reservas em todos os estados da maquina.
     */
    private function restauranteComReservas($clientes): Restaurante
    {
        $restaurante = Restaurante::factory()->comReservas()->create([
            'name' => 'Trattoria Bella',
            'tipo' => 'Italiana',
            'imagem_url' => $this->foto('trattoria-bella.jpg'),
            'email' => 'reservas@deepdish.test',
            'password' => self::SENHA,
        ]);

        $mesas = Mesa::factory()->count(5)->for($restaurante)->create();
        Funcionario::factory()->count(2)->for($restaurante)->create();

        ClienteMesa::factory()
            ->for($clientes[0], 'cliente')->for($mesas[0], 'mesa')
            ->confirmada()->create();

        ClienteMesa::factory()
            ->for($clientes[1], 'cliente')->for($mesas[1], 'mesa')
            ->emAndamento()->create();
        $mesas[1]->update(['status' => 'ocupada']);

        ClienteMesa::factory()
            ->for($clientes[2], 'cliente')->for($mesas[2], 'mesa')
            ->liberada()->create();

        ClienteMesa::factory()
            ->for($clientes[3], 'cliente')->for($mesas[3], 'mesa')
            ->expirada()->create();

        ClienteMesa::factory()
            ->for($clientes[4], 'cliente')->for($mesas[4], 'mesa')
            ->cancelada()->create();

        return $restaurante;
    }

    /**
     * Copia uma foto de database/seeders/imagens pelo mesmo disco do upload de
     * verdade (FILESYSTEM_DISK) — funciona igual no disco local e no Supabase.
     * Nome fixo: rodar o seed de novo sobrescreve em vez de acumular arquivos.
     */
    private function foto(string $arquivo): string
    {
        $caminho = Storage::putFileAs(
            'restaurantes',
            new File(database_path("seeders/imagens/{$arquivo}")),
            "seed-{$arquivo}"
        );

        if (! $caminho) {
            throw new RuntimeException("Não foi possível copiar a foto do seed: {$arquivo}");
        }

        return $caminho;
    }
}
