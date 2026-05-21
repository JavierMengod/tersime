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
            'name'             => $this->faker->words(3, true),
            'user_id'          => User::factory(),
            'operator'         => $this->faker->randomElement(['>', '<', '>=', '<=', '==', '!=']),
            'comparison_value' => $this->faker->randomFloat(2, 10, 500),
            'for_duration'     => 0,
            'is_active'        => true,
            'email_enabled'    => false,
            'telegram_enabled' => false,
            'discord_enabled'  => false,
            'recipient_email'  => null,
        ];
    }

    public function withDuration(int $minutes)
    {
        return $this->state(['for_duration' => $minutes]);
    }

    public function inactive()
    {
        return $this->state(['is_active' => false]);
    }

    public function withOperator(string $operator, float $value)
    {
        return $this->state(['operator' => $operator, 'comparison_value' => $value]);
    }
}
