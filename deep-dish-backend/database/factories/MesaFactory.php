<?php

namespace Database\Factories;

use App\Models\Mesa;
use App\Models\Restaurante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mesa>
 */
class MesaFactory extends Factory
{
    protected $model = Mesa::class;

    /**
     * Contador de numero por restaurante.
     *
     * A tabela tem unique(restaurante_id, numero); sortear o numero causaria
     * colisao ao criar varias mesas do mesmo restaurante. A sequencia garante
     * que Mesa::factory()->count(10)->for($restaurante)->create() funcione.
     *
     * @var array<string, int>
     */
    private static array $proximoNumero = [];

    public function definition(): array
    {
        return [
            'restaurante_id' => Restaurante::factory(),
            'numero' => null, // definido no configure(), que ja conhece o restaurante
            'status' => 'livre',
            'capacidade' => fake()->randomElement([2, 2, 4, 4, 4, 6, 8]),
            'confirmacao' => 'pendente',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Mesa $mesa) {
            if ($mesa->numero !== null) {
                return;
            }

            $chave = (string) $mesa->restaurante_id;

            if (! isset(self::$proximoNumero[$chave])) {
                // Continua de onde a numeracao do restaurante parou, para nao
                // colidir com mesas criadas fora da factory.
                self::$proximoNumero[$chave] = (int) Mesa::where('restaurante_id', $chave)->max('numero');
            }

            $mesa->numero = ++self::$proximoNumero[$chave];
        });
    }

    public function ocupada(): static
    {
        return $this->state(fn () => ['status' => 'ocupada']);
    }

    public function bloqueada(): static
    {
        return $this->state(fn () => ['status' => 'bloqueada']);
    }

    /**
     * Mesa suja esperando alguem comecar a limpar (#171). O state NAO abre linha em
     * 'mesa_limpeza': testes que precisam do ciclo devem passar pelo
     * MesaLimpezaService, que e quem mantem os dois em dia.
     */
    public function aguardandoLimpeza(): static
    {
        return $this->state(fn () => ['status' => 'aguardando_limpeza']);
    }

    public function emLimpeza(): static
    {
        return $this->state(fn () => ['status' => 'em_limpeza']);
    }

    public function comCapacidade(int $lugares): static
    {
        return $this->state(fn () => ['capacidade' => $lugares]);
    }
}
