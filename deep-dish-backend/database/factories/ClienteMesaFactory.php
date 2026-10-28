<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\ClienteMesa;
use App\Models\Mesa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClienteMesa>
 *
 * Reservas vivem aqui — nao existe model `Reserva` no projeto.
 */
class ClienteMesaFactory extends Factory
{
    protected $model = ClienteMesa::class;

    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'mesa_id' => Mesa::factory(),
            // Guardado em UTC, como ReservaController::store faz.
            'horario_reserva' => now()->utc()->addHours(2)->startOfHour(),
            'horario_checkin' => null,
            'party_size' => fake()->randomElement([1, 2, 2, 4, 4, 6]),
            'status' => 'confirmada',
        ];
    }

    /**
     * Reserva feita, cliente ainda nao chegou. E o estado inicial real:
     * ReservaController::store cria assim, e a mesa continua 'livre'.
     */
    public function confirmada(): static
    {
        return $this->state(fn () => [
            'status' => 'confirmada',
            'horario_checkin' => null,
        ]);
    }

    /**
     * Cliente fez check-in e esta na mesa.
     */
    public function emAndamento(int $chegouHaMinutos = 30): static
    {
        return $this->state(fn () => [
            'status' => 'em_andamento',
            'horario_checkin' => now()->subMinutes($chegouHaMinutos),
        ]);
    }

    /**
     * Sessao encerrada — o cliente ocupou a mesa e saiu.
     *
     * Passa por registrarSaida() do model em vez de escrever horario_saida e
     * duracao_segundos na mao, para que a duracao seja calculada pela mesma
     * regra que o codigo de producao usa.
     */
    public function liberada(int $ficouMinutos = 75): static
    {
        return $this->state(fn () => [
            'status' => 'liberada',
            'horario_checkin' => now()->subMinutes($ficouMinutos),
        ])->afterCreating(function (ClienteMesa $reserva) {
            $reserva->registrarSaida();
        });
    }

    /**
     * No-show: passou da tolerancia sem check-in.
     *
     * duracao_segundos fica nulo de proposito — zero seria lido como "sentou e
     * saiu na hora" e contaminaria a media de permanencia.
     */
    public function expirada(): static
    {
        return $this->state(fn () => [
            'status' => 'expirada',
            'horario_reserva' => now()->utc()->subHours(3),
            'horario_checkin' => null,
        ]);
    }

    public function cancelada(): static
    {
        return $this->state(fn () => [
            'status' => 'cancelada',
            'horario_checkin' => null,
        ]);
    }
}
