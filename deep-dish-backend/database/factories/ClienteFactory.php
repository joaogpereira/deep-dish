<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    /**
     * O locale e fixado em pt_BR de proposito, em vez de depender de
     * APP_FAKER_LOCALE: .env ja existentes nao mudam quando o .env.example muda,
     * e isso faria as factories gerarem dados diferentes por desenvolvedor.
     */
    public function definition(): array
    {
        return [
            'name' => fake('pt_BR')->name(),
            'email' => fake('pt_BR')->unique()->safeEmail(),
            'cpf' => fake('pt_BR')->unique()->cpf(),
            // O cast 'hashed' do model cuida do bcrypt — aqui vai texto puro.
            'password' => 'senha-de-teste',
            'email_verified_at' => now(),
            'token_version' => 0,
        ];
    }

    /**
     * Cliente que ainda nao confirmou o e-mail. O middleware
     * EnsureEmailIsVerified bloqueia esses com 403 + email_not_verified.
     */
    public function naoVerificado(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
