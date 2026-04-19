<?php

namespace Database\Factories;

use App\Models\Playlist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Playlist>
 */
class PlaylistFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'folder_id' => null,
            'spotify_id' => fake()->unique()->lexify('playlist_????????'),
            'name' => fake()->words(3, true),
            'description' => fake()->boolean(70) ? fake()->sentence() : null,
            'images' => fake()->boolean(70) ? [['url' => fake()->imageUrl(300, 300, 'music')]] : null,
            'owner_spotify_id' => fake()->lexify('owner_????????'),
            'owner_name' => fake()->name(),
            'is_public' => fake()->boolean(),
            'is_collaborative' => fake()->boolean(20),
            'tracks_total' => fake()->numberBetween(0, 120),
            'snapshot_id' => fake()->sha1(),
            'uri' => fake()->boolean(80) ? 'spotify:playlist:'.fake()->lexify('????????') : null,
            'external_urls' => ['spotify' => fake()->url()],
            'synced_at' => now()->subMinutes(fake()->numberBetween(0, 120)),
            'expires_at' => now()->addMinutes(fake()->numberBetween(5, 180)),
        ];
    }
}
