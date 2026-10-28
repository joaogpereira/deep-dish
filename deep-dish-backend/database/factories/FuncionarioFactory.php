<?php

namespace Database\Factories;

use App\Models\Funcionario;
use App\Models\Restaurante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Funcionario>
 */
class FuncionarioFactory extends Factory
{
    protected $model = Funcionario::class;

    public function definition(): array
    {
        return [
            'restaurante_id' => Restaurante::factory(),
            'name' => fake('pt_BR')->name(),
            'cargo' => fake()->randomElement([
                'Garçom', 'Recepcionista', 'Gerente', 'Cozinheiro', 'Auxiliar de limpeza',
            ]),
            // unique(restaurante_id, cpf) — o unique() global do faker ja basta,
            // porque garante CPF distinto em toda a execucao.
            'cpf' => fake('pt_BR')->unique()->cpf(),
            'telefone' => fake('pt_BR')->cellphoneNumber(),
            'email' => fake('pt_BR')->unique()->safeEmail(),
            'data_nascimento' => fake()->dateTimeBetween('-55 years', '-18 years')->format('Y-m-d'),
            'horario' => fake()->randomElement(['08:00-16:00', '12:00-20:00', '16:00-00:00']),
            'observacoes' => null,
            'ativo' => true,
            'motivo_afastamento' => null,
        ];
    }

    public function afastado(string $motivo = 'Licença médica'): static
    {
        return $this->state(fn () => [
            'ativo' => false,
            'motivo_afastamento' => $motivo,
        ]);
    }
}
