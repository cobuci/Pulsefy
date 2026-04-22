<?php

namespace Database\Factories;

use App\Models\DiscoveryLikedTrack;
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
            'spotify_id' => $this->faker->unique()->regexify('[A-Za-z0-9]{22}'),
            'name' => $this->faker->words(3, true),
            'artist_name' => $this->faker->name(),
            'album_name' => $this->faker->words(2, true),
            'image_url' => $this->faker->imageUrl(300, 300),
            'liked_at' => now(),
        ];
    }
}
