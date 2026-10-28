<?php

namespace Database\Factories;

use App\Models\Fila;
use App\Models\Restaurante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fila>
 */
class FilaFactory extends Factory
{
    protected $model = Fila::class;

    public function definition(): array
    {
        return [
            'restaurante_id' => Restaurante::factory(),
            // FilaService::enfileirar agrupa por (restaurante_id, horario_reserva)
            // e guarda em UTC. A hora cheia evita filas duplicadas por diferenca
            // de segundos ao criar varios registros no mesmo cenario.
            'horario_reserva' => now()->utc()->addHour()->startOfHour(),
            'status' => Fila::STATUS_ABERTA,
        ];
    }

    public function encerrada(): static
    {
        return $this->state(fn () => ['status' => Fila::STATUS_ENCERRADA]);
    }

    public function noHorario(string $horario): static
    {
        return $this->state(fn () => ['horario_reserva' => $horario]);
    }
}
