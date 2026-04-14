<?php

namespace Database\Factories;

use App\Models\SpotifyStat;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpotifyStat>
 */
class SpotifyStatFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['top_tracks', 'top_artists', 'recently_played']),
            'time_range' => fake()->randomElement(['short_term', 'medium_term', 'long_term', 'none']),
            'payload' => [],
            'fetched_at' => now(),
            'expires_at' => now()->addDay(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subHour(),
        ]);
    }

    public function forTopTracks(string $timeRange = 'medium_term'): static
    {
        return $this->state(fn () => [
            'type' => 'top_tracks',
            'time_range' => $timeRange,
        ]);
    }

    public function forTopArtists(string $timeRange = 'medium_term'): static
    {
        return $this->state(fn () => [
            'type' => 'top_artists',
            'time_range' => $timeRange,
        ]);
    }

    public function forRecentlyPlayed(): static
    {
        return $this->state(fn () => [
            'type' => 'recently_played',
            'time_range' => 'none',
            'expires_at' => now()->addMinutes(15),
        ]);
    }
}
