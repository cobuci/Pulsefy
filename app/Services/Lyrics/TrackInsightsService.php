<?php

namespace App\Services\Lyrics;

use App\Ai\Agents\TrackInsightsAgent;
use Illuminate\Support\Facades\File;

final class TrackInsightsService
{
    /**
     * @return array{
     *     summary_en: string,
     *     summary_pt: string,
     *     mood_en: string,
     *     mood_pt: string,
     *     meaning_en: string,
     *     meaning_pt: string,
     *     themes_en: string[],
     *     themes_pt: string[],
     *     trivia_en: string[],
     *     trivia_pt: string[],
     *     similar_en: string[],
     *     similar_pt: string[],
     *     provider: string,
     *     model: string,
     * }
     */
    public function generate(string $artist, string $trackName, string $albumName = ''): array
    {
        $prompt = $this->buildPrompt($artist, $trackName, $albumName);
        $model = (string) config('services.track_insights.model', 'gemini-3.1-flash-lite-preview');
        $provider = (string) config('services.track_insights.provider', 'gemini');

        $response = (new TrackInsightsAgent)->prompt($prompt, model: $model);

        return [
            'summary_en' => (string) ($response['summary_en'] ?? ''),
            'summary_pt' => (string) ($response['summary_pt'] ?? ''),
            'mood_en' => (string) ($response['mood_en'] ?? ''),
            'mood_pt' => (string) ($response['mood_pt'] ?? ''),
            'meaning_en' => (string) ($response['meaning_en'] ?? ''),
            'meaning_pt' => (string) ($response['meaning_pt'] ?? ''),
            'themes_en' => array_values(array_filter((array) ($response['themes_en'] ?? []), 'is_string')),
            'themes_pt' => array_values(array_filter((array) ($response['themes_pt'] ?? []), 'is_string')),
            'trivia_en' => array_values(array_filter((array) ($response['trivia_en'] ?? []), 'is_string')),
            'trivia_pt' => array_values(array_filter((array) ($response['trivia_pt'] ?? []), 'is_string')),
            'similar_en' => array_values(array_filter((array) ($response['similar_en'] ?? []), 'is_string')),
            'similar_pt' => array_values(array_filter((array) ($response['similar_pt'] ?? []), 'is_string')),
            'provider' => $provider,
            'model' => $model,
        ];
    }

    private function buildPrompt(string $artist, string $trackName, string $albumName): string
    {
        $template = File::get(resource_path('prompts/track/insights.user.md'));

        return strtr($template, [
            '{{artist}}' => $artist,
            '{{track}}' => $trackName,
            '{{album}}' => $albumName,
        ]);
    }
}
