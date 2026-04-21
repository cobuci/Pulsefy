<?php

namespace Database\Factories;

use App\Enums\TrackInsightStatus;
use App\Models\TrackInsight;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrackInsight>
 */
class TrackInsightFactory extends Factory
{
    public function definition(): array
    {
        return [
            'track_id' => $this->faker->unique()->regexify('[A-Za-z0-9]{22}'),
            'track_name' => $this->faker->words(3, true),
            'artist_name' => $this->faker->name(),
            'album_name' => $this->faker->words(2, true),
            'status' => TrackInsightStatus::Queued,
            'summary' => null,
            'mood' => null,
            'meaning' => null,
            'themes' => null,
            'trivia' => null,
            'similar' => null,
            'provider' => null,
            'model' => null,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function ready(): static
    {
        return $this->state([
            'status' => TrackInsightStatus::Ready,
            'summary' => 'A great song about life.',
            'summary_pt' => 'Uma ótima música sobre a vida.',
            'mood' => 'melancholic',
            'mood_pt' => 'melancólico',
            'meaning' => 'About loss and longing.',
            'meaning_pt' => 'Sobre perda e saudade.',
            'themes' => ['love', 'longing'],
            'themes_pt' => ['amor', 'saudade'],
            'trivia' => ['Released in 1999'],
            'trivia_pt' => ['Lançada em 1999'],
            'similar' => ['Artist X'],
            'similar_pt' => ['Artista X'],
            'provider' => 'gemini',
            'model' => 'gemini-3.1-flash-lite-preview',
            'started_at' => now()->subSeconds(5),
            'completed_at' => now(),
        ]);
    }

    public function processing(): static
    {
        return $this->state([
            'status' => TrackInsightStatus::Processing,
            'started_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => TrackInsightStatus::Failed,
            'error_message' => 'Generation failed.',
            'completed_at' => now(),
        ]);
    }
}
