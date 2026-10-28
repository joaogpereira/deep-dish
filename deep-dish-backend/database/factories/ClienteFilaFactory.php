<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\ClienteFila;
use App\Models\Fila;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClienteFila>
 */
class ClienteFilaFactory extends Factory
{
    protected $model = ClienteFila::class;

    public function definition(): array
    {
        return [
            'fila_id' => Fila::factory(),
            'cliente_id' => Cliente::factory(),
            'qntd_pessoas' => fake()->randomElement([1, 2, 2, 3, 4, 4, 6]),
        ];
    }

    /**
     * Entrou na fila ha X minutos.
     *
     * A posicao na fila e derivada de created_at (ClienteFila::getPosicaoAttribute),
     * entao controlar esse campo e a unica forma de montar uma ordem previsivel.
     */
    public function entrouHa(int $minutos): static
    {
        return $this->state(fn () => ['created_at' => now()->subMinutes($minutos)]);
    }

    /**
     * Saiu da fila porque foi atendido (promovido para uma mesa).
     */
    public function atendido(int $esperouMinutos = 20): static
    {
        return $this->saiu(ClienteFila::STATUS_SAIDA_ATENDIDO, $esperouMinutos);
    }

    /**
     * Saiu da fila por conta propria.
     */
    public function desistiu(int $esperouMinutos = 35): static
    {
        return $this->saiu(ClienteFila::STATUS_SAIDA_DESISTIU, $esperouMinutos);
    }

    /**
     * Removido da fila pelo restaurante.
     */
    public function removido(int $esperouMinutos = 15): static
    {
        return $this->saiu(ClienteFila::STATUS_SAIDA_REMOVIDO, $esperouMinutos);
    }

    /**
     * No-show: foi chamado para a mesa e nao apareceu.
     *
     * Em producao quem grava este status e a 'fila:expirar-chamados' (#164),
     * reclassificando um 'atendido' chamado que nao fez check-in. Aqui o status
     * vai direto, sem chamada, porque a taxa de abandono do AnalyticsService so
     * precisa do status: soma 'desistiu' + 'expirado'.
     */
    public function expirou(int $esperouMinutos = 25): static
    {
        return $this->saiu(ClienteFila::STATUS_SAIDA_EXPIRADO, $esperouMinutos);
    }

    /**
     * Registra a saida usando a MESMA porta que o codigo de producao usa.
     *
     * Escrever status_saida/saiu_em direto no banco funcionaria, mas burlaria o
     * hook `deleting` do model — que existe justamente para impedir registro
     * fantasma na fila. Passando por registrarSaida(), a factory acompanha
     * qualquer mudanca futura nessa regra.
     *
     * O created_at precisa estar no passado ANTES da chamada: registrarSaida()
     * calcula tempo_espera_segundos como created_at -> agora. Sem isso todo
     * registro historico sairia com espera de 0 segundo, o que inutilizaria as
     * metricas da issue #161 (AnalyticsService).
     */
    private function saiu(string $status, int $esperouMinutos): static
    {
        return $this->state(fn (array $atributos) => [
            'created_at' => $atributos['created_at'] ?? now()->subMinutes($esperouMinutos),
        ])->afterCreating(function (ClienteFila $registro) use ($status) {
            $registro->registrarSaida($status);
        });
    }
}
