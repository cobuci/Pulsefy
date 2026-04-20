<?php

namespace Database\Factories;

use App\Models\Lyric;
use App\Models\LyricTranslation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LyricTranslation>
 */
class LyricTranslationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'lyric_id' => Lyric::factory(),
            'track_id' => $this->faker->unique()->regexify('[A-Za-z0-9_]{8,24}'),
            'status' => LyricTranslation::STATUS_QUEUED,
            'translated_lines' => null,
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
            'status' => LyricTranslation::STATUS_READY,
            'translated_lines' => [
                [
                    'index' => 0,
                    'timestamp' => '00:01.00',
                    'text' => 'Hello from the other side',
                    'source_lang' => 'en',
                    'pt_br' => 'Olá, do outro lado',
                    'en' => null,
                ],
            ],
            'provider' => 'gemini',
            'model' => 'gemini-3.1-flash-lite-preview',
            'started_at' => now()->subSeconds(2),
            'completed_at' => now(),
        ]);
    }
}
