<?php

namespace Database\Factories;

use App\Models\Restaurante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Restaurante>
 */
class RestauranteFactory extends Factory
{
    protected $model = Restaurante::class;

    public function definition(): array
    {
        // Abre entre 10h e 12h e fecha entre 21h e 23h — sempre coerente,
        // porque ReservaController valida o horario da reserva contra a janela.
        $abertura = fake()->numberBetween(10, 12);
        $fechamento = fake()->numberBetween(21, 23);

        return [
            'name' => fake('pt_BR')->company(),
            'email' => fake('pt_BR')->unique()->companyEmail(),
            'cnpj' => fake('pt_BR')->unique()->cnpj(),
            'tipo' => fake()->randomElement([
                'Italiana', 'Japonesa', 'Brasileira', 'Árabe', 'Mexicana', 'Pizzaria',
            ]),
            'logradouro' => fake('pt_BR')->streetName(),
            'numero' => (string) fake()->numberBetween(1, 2000),
            'complemento' => fake()->optional()->secondaryAddress(),
            'bairro' => fake('pt_BR')->citySuffix(),
            'cidade' => fake('pt_BR')->city(),
            'estado' => fake('pt_BR')->stateAbbr(),
            'cep' => fake('pt_BR')->postcode(),
            'telefone' => fake('pt_BR')->cellphoneNumber(),
            'imagem_url' => null,
            'horario_abertura' => sprintf('%02d:00:00', $abertura),
            'horario_fechamento' => sprintf('%02d:00:00', $fechamento),
            'fila_ativa' => false,
            'rating' => fake()->randomFloat(1, 3.0, 5.0),   // decimal(2,1)
            'price_range' => fake()->numberBetween(1, 4),   // tinyInteger
            'reservations_enabled' => false,
            'description' => fake('pt_BR')->sentence(12),
            'tipo_usuario' => 'restaurante',
            'password' => 'senha-de-teste',
            'email_verified_at' => now(),
            'token_version' => 0,
        ];
    }

    /**
     * Restaurante com fila virtual ligada.
     *
     * Atencao: FilaController::store nao valida essa flag hoje — a trava existe
     * so no frontend. Ver a issue #168.
     */
    public function comFilaAtiva(): static
    {
        return $this->state(fn () => ['fila_ativa' => true]);
    }

    /**
     * Aceita reservas. Sem isso, ReservaController::store recusa.
     */
    public function comReservas(): static
    {
        return $this->state(fn () => ['reservations_enabled' => true]);
    }

    public function naoVerificado(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
