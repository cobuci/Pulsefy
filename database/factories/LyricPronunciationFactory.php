<?php

namespace Database\Factories;

use App\Models\Lyric;
use App\Models\LyricPronunciation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LyricPronunciation>
 */
class LyricPronunciationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'lyric_id' => Lyric::factory(),
            'track_id' => $this->faker->unique()->regexify('[A-Za-z0-9_]{8,24}'),
            'status' => LyricPronunciation::STATUS_QUEUED,
            'romanized_lines' => null,
            'provider' => null,
            'model' => null,
            'requested_at' => now(),
            'started_at' => null,
            'completed_at' => null,
            'error_message' => null,
        ];
    }

    public function ready(): static
    {
        return $this->state([
            'status' => LyricPronunciation::STATUS_READY,
            'romanized_lines' => [
                [
                    'index' => 0,
                    'timestamp' => '00:01.00',
                    'pt_br' => 'privet mir',
                    'en' => 'prih-vyet meer',
                ],
            ],
            'provider' => 'gemini',
            'model' => 'gemini-3.1-flash-lite-preview',
            'started_at' => now()->subSeconds(2),
            'completed_at' => now(),
        ]);
    }
}
