<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AlertLogFactory extends Factory
{
    public function definition()
    {
        return [
            'user_id'        => User::factory(),
            'rule_id'        => null,
            'rule_name'      => implode(' ', $this->faker->words(3)),
            'dispositivo_id' => null,
            'device_name'    => strtoupper($this->faker->bothify('DEVICE-????-####')),
            'type'           => $this->faker->randomElement(['firing', 'resolution']),
            'channels'       => null,
            'message'        => $this->faker->sentence(),
        ];
    }

    public function firing()
    {
        return $this->state(['type' => 'firing']);
    }

    public function resolution()
    {
        return $this->state(['type' => 'resolution']);
    }

    public function withChannels(array $channels)
    {
        return $this->state(['channels' => $channels]);
    }
}
