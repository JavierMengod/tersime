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
            'nombre'            => $this->faker->words(3, true),
            'user_id'           => User::factory(),
            'operador'          => $this->faker->randomElement(['>', '<', '>=', '<=', '==', '!=']),
            'valor_comparacion' => $this->faker->randomFloat(2, 10, 500),
            'duracion'          => 0,
            'activo'            => true,
            'correo_activo'     => false,
            'telegram_activo'   => false,
            'discord_activo'    => false,
            'correo_destinatario' => null,
        ];
    }

    public function withDuration(int $minutes)
    {
        return $this->state(['duracion' => $minutes]);
    }

    public function inactive()
    {
        return $this->state(['activo' => false]);
    }

    public function withOperator(string $operator, float $value)
    {
        return $this->state(['operador' => $operator, 'valor_comparacion' => $value]);
    }
}
