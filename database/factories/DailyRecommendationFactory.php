<?php

namespace Database\Factories;

use App\Models\DailyRecommendation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyRecommendation>
 */
class DailyRecommendationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'generated_at' => now(),
        ];
    }

    public function forToday(): static
    {
        return $this->state([
            'date' => now()->toDateString(),
            'generated_at' => now(),
        ]);
    }
}
