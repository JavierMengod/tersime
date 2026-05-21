<?php

namespace Database\Factories;

use App\Models\Regla;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReglaFactory extends Factory
{
    protected $model = Regla::class;

    public function definition()
    {
        return [
            'nombre'              => $this->faker->words(3, true),
            'user_id'             => User::factory(),
            'operador'            => $this->faker->randomElement(['>', '<', '>=', '<=', '==', '!=']),
            'valor_comparacion'   => $this->faker->randomFloat(2, 10, 500),
            'duracion'            => 0,
            'activo'              => true,
            'correo_activo'       => false,
            'telegram_activo'     => false,
            'discord_activo'      => false,
            'correo_destinatario' => null,
        ];
    }

    public function conDuracion(int $minutos): static
    {
        return $this->state(['duracion' => $minutos]);
    }

    public function inactiva(): static
    {
        return $this->state(['activo' => false]);
    }

    public function conOperador(string $operador, float $valor): static
    {
        return $this->state(['operador' => $operador, 'valor_comparacion' => $valor]);
    }
}
