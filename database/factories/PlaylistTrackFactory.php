<?php

namespace Database\Factories;

use App\Models\Playlist;
use App\Models\PlaylistTrack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlaylistTrack>
 */
class PlaylistTrackFactory extends Factory
{
    public function definition(): array
    {
        return [
            'playlist_id' => Playlist::factory(),
            'track_id' => null,
            'spotify_track_id' => fake()->lexify('track_????????'),
            'position' => fake()->numberBetween(0, 100),
            'added_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'added_by_spotify_id' => fake()->boolean(70) ? fake()->lexify('user_????????') : null,
        ];
    }
}
