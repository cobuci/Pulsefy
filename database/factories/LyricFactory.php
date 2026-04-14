<?php

namespace Database\Factories;

use App\Models\Lyric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lyric>
 */
class LyricFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'track_id' => $this->faker->unique()->regexify('[A-Za-z0-9]{22}'),
            'artist_name' => $this->faker->name(),
            'track_name' => $this->faker->sentence(3),
            'synced_lyrics' => "[00:12.00] Hello, it's me\n[00:17.50] I was wondering if after all",
            'plain_lyrics' => "Hello, it's me\nI was wondering if after all",
            'is_synced' => true,
            'source' => 'lrclib',
            'fetched_at' => now(),
        ];
    }

    public function noLyrics(): static
    {
        return $this->state([
            'synced_lyrics' => null,
            'plain_lyrics' => null,
            'is_synced' => false,
        ]);
    }

    public function plainOnly(): static
    {
        return $this->state([
            'synced_lyrics' => null,
            'plain_lyrics' => "Hello, it's me\nI was wondering if after all",
            'is_synced' => false,
        ]);
    }
}
