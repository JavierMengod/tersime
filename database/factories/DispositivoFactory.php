<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DispositivoFactory extends Factory
{
    public function definition()
    {
        return [
            'influx_tag' => strtoupper($this->faker->bothify('DEVICE-????-####')),
        ];
    }
}
