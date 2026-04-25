<?php

namespace Database\Factories;

use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Track>
 */
class TrackFactory extends Factory
{
    public function definition(): array
    {
        return [
            'spotify_id' => $this->faker->unique()->regexify('[A-Za-z0-9]{22}'),
            'name' => $this->faker->words(3, true),
            'duration_ms' => $this->faker->numberBetween(120000, 360000),
            'explicit' => false,
            'image_url' => $this->faker->imageUrl(300, 300),
            'album_id' => null,
        ];
    }
}
