<?php

namespace Database\Factories;

use App\Enums\DailyRecommendationStatus;
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
            'status' => DailyRecommendationStatus::Ready,
            'started_at' => now(),
            'error_message' => null,
        ];
    }

    public function forToday(): static
    {
        return $this->state([
            'date' => now()->toDateString(),
            'generated_at' => now(),
        ]);
    }

    public function processing(): static
    {
        return $this->state([
            'status' => DailyRecommendationStatus::Processing,
            'started_at' => now(),
            'error_message' => null,
        ]);
    }

    public function failed(?string $message = 'Generation failed.'): static
    {
        return $this->state([
            'status' => DailyRecommendationStatus::Failed,
            'started_at' => now()->subMinutes(5),
            'error_message' => $message,
        ]);
    }

    public function empty(): static
    {
        return $this->state([
            'status' => DailyRecommendationStatus::Empty,
            'started_at' => now()->subMinute(),
            'error_message' => null,
        ]);
    }
}
