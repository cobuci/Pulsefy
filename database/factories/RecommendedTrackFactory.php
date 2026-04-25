<?php

namespace Database\Factories;

use App\Models\DailyRecommendation;
use App\Models\RecommendedTrack;
use App\Models\Track;
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
            'track_id' => Track::factory(),
            'match_score' => $this->faker->numberBetween(10, 100),
            'position' => $this->faker->numberBetween(1, 50),
        ];
    }
}
