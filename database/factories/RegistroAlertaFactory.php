<?php

namespace Database\Factories;

use App\Models\RegistroAlerta;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RegistroAlertaFactory extends Factory
{
    protected $model = RegistroAlerta::class;

    public function definition()
    {
        return [
            'user_id'            => User::factory(),
            'regla_id'           => null,
            'nombre_regla'       => implode(' ', $this->faker->words(3)),
            'dispositivo_id'     => null,
            'nombre_dispositivo' => strtoupper($this->faker->bothify('DEVICE-????-####')),
            'tipo'               => $this->faker->randomElement(['firing', 'resolution']),
            'canales'            => null,
            'mensaje'            => $this->faker->sentence(),
        ];
    }

    public function enDisparo(): static
    {
        return $this->state(['tipo' => 'firing']);
    }

    public function resuelta(): static
    {
        return $this->state(['tipo' => 'resolution']);
    }

    public function conCanales(array $canales): static
    {
        return $this->state(['canales' => $canales]);
    }
}
