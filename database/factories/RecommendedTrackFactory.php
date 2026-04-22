<?php

namespace Database\Factories;

use App\Models\DailyRecommendation;
use App\Models\RecommendedTrack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecommendedTrack>
 */
class RecommendedTrackFactory extends Factory
{
    public function definition(): array
    {
        return [
            'daily_recommendation_id' => DailyRecommendation::factory(),
            'spotify_id' => $this->faker->unique()->regexify('[A-Za-z0-9]{22}'),
            'name' => $this->faker->words(3, true),
            'artist_name' => $this->faker->name(),
            'album_name' => $this->faker->words(2, true),
            'image_url' => $this->faker->imageUrl(300, 300),
            'preview_url' => null,
            'match_score' => $this->faker->numberBetween(10, 100),
            'position' => $this->faker->numberBetween(1, 50),
        ];
    }
}
