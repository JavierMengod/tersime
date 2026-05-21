<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProgramacionInformesFactory extends Factory
{
    public function definition()
    {
        return [
            'user_id'             => User::factory(),
            'nombre'              => $this->faker->words(3, true),
            'tipo_periodo'        => 'horas',
            'valor_periodo'       => 1,
            'hora_inicio'         => null,
            'telegram'            => false,
            'discord'             => false,
            'correo'              => false,
            'correo_destino'      => null,
            'activo'              => true,
            'ultima_ejecucion_at' => null,
        ];
    }

    public function diaria(string $hora = '09:00'): static
    {
        return $this->state([
            'tipo_periodo'  => 'dias',
            'valor_periodo' => 1,
            'hora_inicio'   => $hora,
        ]);
    }

    public function mensual(string $hora = '09:00'): static
    {
        return $this->state([
            'tipo_periodo'  => 'meses',
            'valor_periodo' => 1,
            'hora_inicio'   => $hora,
        ]);
    }

    public function porHoras(int $horas = 1): static
    {
        return $this->state([
            'tipo_periodo'  => 'horas',
            'valor_periodo' => $horas,
            'hora_inicio'   => null,
        ]);
    }

    public function inactiva(): static
    {
        return $this->state(['activo' => false]);
    }
}
