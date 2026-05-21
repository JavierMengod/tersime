<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DispositivoFactory extends Factory
{
    public function definition()
    {
        return [
            'etiqueta_influx' => strtoupper($this->faker->bothify('DEVICE-????-####')),
        ];
    }
}
