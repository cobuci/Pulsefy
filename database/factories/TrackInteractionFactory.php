<?php

namespace Database\Factories;

use App\Models\Track;
use App\Models\TrackInteraction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrackInteraction>
 */
class TrackInteractionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'track_id' => Track::factory(),
            'type' => 'like',
            'interacted_at' => now(),
            'expires_at' => null,
        ];
    }

    public function like(): static
    {
        return $this->state([
            'type' => 'like',
            'expires_at' => null,
        ]);
    }

    public function skip(): static
    {
        return $this->state([
            'type' => 'skip',
            'interacted_at' => now(),
            'expires_at' => now()->addDays(14),
        ]);
    }

    public function expiredSkip(): static
    {
        return $this->state([
            'type' => 'skip',
            'interacted_at' => now()->subDays(20),
            'expires_at' => now()->subDays(6),
        ]);
    }
}
