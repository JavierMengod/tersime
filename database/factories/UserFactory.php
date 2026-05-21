<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition()
    {
        return [
            'name'           => $this->faker->unique()->userName(),
            'email'          => $this->faker->unique()->safeEmail(),
            'password'       => bcrypt('password'),
            'language'       => 'es',
            'timezone'       => 'Europe/Madrid',
            'theme'          => 'light',
            'admin'          => false,
            'enabled'        => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function administrador(): static
    {
        return $this->state(['admin' => true]);
    }

    public function deshabilitado(): static
    {
        return $this->state(['enabled' => false]);
    }
}
