<?php

namespace Database\Factories;

use App\Models\SimilarityCache;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SimilarityCache>
 */
class SimilarityCacheFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement(['artist', 'track']),
            'key' => $this->faker->unique()->slug(3),
            'payload' => [['name' => $this->faker->name(), 'match' => $this->faker->randomFloat(3, 0, 1)]],
            'fetched_at' => now(),
            'expires_at' => now()->addDays(30),
        ];
    }

    public function stale(): static
    {
        return $this->state([
            'fetched_at' => now()->subDays(35),
            'expires_at' => now()->subDays(5),
        ]);
    }

    public function artist(): static
    {
        return $this->state(['type' => 'artist']);
    }

    public function track(): static
    {
        return $this->state(['type' => 'track']);
    }
}
