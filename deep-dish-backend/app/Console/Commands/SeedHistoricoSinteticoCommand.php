<?php

namespace App\Console\Commands;

use App\Models\Restaurante;
use Database\Seeders\HistoricoSinteticoSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Str;

/**
 * Gera histórico sintético de fila e reservas para um restaurante.
 *
 * A geração SEMPRE limpa o dado sintético anterior do mesmo restaurante antes
 * de escrever. É o que sustenta o critério de aceite "rodar duas vezes não
 * duplica nem corrompe": não existe modo append.
 */
class SeedHistoricoSinteticoCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'historico:sintetico
        {--restaurante= : ID (UUID) ou e-mail do restaurante alvo}
        {--semanas=12 : Quantas semanas de histórico gerar}
        {--limpar : Apenas remove o histórico sintético, sem gerar}
        {--force : Executa sem confirmação em ambiente de produção}';

    protected $description = 'Gera ~12 semanas de fila e reservas encerradas com curvas realistas, marcadas como sintéticas.';

    public function handle(HistoricoSinteticoSeeder $seeder): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $restaurante = $this->resolverRestaurante();

        if (! $restaurante) {
            return self::FAILURE;
        }

        $semanas = (int) $this->option('semanas');

        if ($semanas < 1 || $semanas > 104) {
            $this->error('--semanas precisa estar entre 1 e 104.');

            return self::FAILURE;
        }

        $this->line("Restaurante: <info>{$restaurante->name}</info> ({$restaurante->id})");

        $removido = $seeder->limpar($restaurante);
        $this->line(sprintf(
            'Histórico sintético anterior removido: %d fila(s), %d entrada(s), %d reserva(s).',
            $removido['filas'],
            $removido['entradas'],
            $removido['reservas'],
        ));

        if ($this->option('limpar')) {
            $this->info('Limpeza concluída.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line("Gerando {$semanas} semana(s)... (pode levar alguns minutos em banco remoto)");

        $barra = $this->output->createProgressBar($semanas * 7);
        $barra->start();

        try {
            $total = $seeder->gerar(
                $restaurante,
                $semanas,
                fn (int $atual, int $totalDias) => $barra->setProgress($atual),
            );
        } catch (\RuntimeException $e) {
            $barra->clear();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $barra->finish();
        $this->newLine(2);

        $this->info('Histórico sintético gerado:');
        $this->line("  Dias      {$total['dias']}");
        $this->line("  Filas     {$total['filas']}");
        $this->line("  Entradas  {$total['entradas']}");
        $this->line("  Reservas  {$total['reservas']}");
        $this->newLine();
        $this->comment('Marcado com origem = \''.HistoricoSinteticoSeeder::ORIGEM.'\' em fila, clientefila e clientemesa.');
        $this->comment('Lembrete: fila encerrada é soft-deleted — consultas de histórico precisam de withTrashed().');

        return self::SUCCESS;
    }

    /**
     * Aceita UUID ou e-mail. Sem a opção, lista os restaurantes disponíveis em
     * vez de adivinhar um alvo.
     */
    private function resolverRestaurante(): ?Restaurante
    {
        $alvo = $this->option('restaurante');

        if (! $alvo) {
            $this->error('Informe o restaurante alvo com --restaurante=<uuid|email>.');
            $this->listarRestaurantes();

            return null;
        }

        // O Postgres recusa comparar a coluna uuid com texto livre
        // ("invalid input syntax for type uuid"), então o formato do argumento
        // decide a coluna em vez de um orWhere que tentaria as duas.
        $restaurante = Str::isUuid($alvo)
            ? Restaurante::find($alvo)
            : Restaurante::where('email', $alvo)->first();

        if (! $restaurante) {
            $this->error("Restaurante '{$alvo}' não encontrado.");
            $this->listarRestaurantes();

            return null;
        }

        return $restaurante;
    }

    private function listarRestaurantes(): void
    {
        $restaurantes = Restaurante::query()
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email']);

        if ($restaurantes->isEmpty()) {
            $this->line('Nenhum restaurante cadastrado.');

            return;
        }

        $this->newLine();
        $this->table(
            ['ID', 'Nome', 'E-mail'],
            $restaurantes->map(fn (Restaurante $r) => [
                $r->id,
                $r->name,
                $r->makeVisible('email')->email,
            ])->all(),
        );
    }
}
