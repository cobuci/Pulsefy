<?php

namespace Database\Factories;

use App\Models\DiscoveryLikedTrack;
use App\Models\Track;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscoveryLikedTrack>
 */
class DiscoveryLikedTrackFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'track_id' => Track::factory(),
            'artist_name' => fake()->name(),
            'liked_at' => now(),
        ];
    }
}
