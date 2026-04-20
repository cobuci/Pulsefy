<?php

namespace App\Services\Lyrics;

use App\Ai\Agents\LyricsTranslationAgent;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class LyricsTranslationService
{
    public function __construct(
        private readonly LrcLineParser $lineParser,
    ) {}

    /**
     * @return array{provider: string, model: string, lines: array<int, array{index: int, timestamp: ?string, text: string, source_lang: string, pt_br: ?string, en: ?string}>}
     */
    public function translate(
        string $artist,
        string $trackName,
        string $lyrics,
    ): array {
        $lines = $this->lineParser->parse($lyrics);

        if ($lines === []) {
            throw new RuntimeException('No lyric lines available for translation.');
        }

        $prompt = $this->buildPrompt($artist, $trackName, $lines);
        $model = (string) config('services.lyrics_translation.model', 'gemini-3.1-flash-lite-preview');
        $provider = (string) config('services.lyrics_translation.provider', 'gemini');

        $response = (new LyricsTranslationAgent)->prompt($prompt, model: $model);
        $translatedLines = $response['lines'] ?? [];

        return [
            'provider' => $provider,
            'model' => $model,
            'lines' => $this->mergeWithOriginalLines($lines, is_array($translatedLines) ? $translatedLines : []),
        ];
    }

    /**
     * @param  array<int, array{index: int, timestamp: ?string, text: string}>  $originalLines
     */
    private function buildPrompt(string $artist, string $trackName, array $originalLines): string
    {
        $template = File::get(resource_path('prompts/lyrics/translation.user.md'));

        return strtr($template, [
            '{{artist}}' => $artist,
            '{{track}}' => $trackName,
            '{{lines_json}}' => json_encode($originalLines, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]',
        ]);
    }

    /**
     * @param  array<int, array{index: int, timestamp: ?string, text: string}>  $originalLines
     * @param  array<int, array<string, mixed>>  $translatedLines
     * @return array<int, array{index: int, timestamp: ?string, text: string, source_lang: string, pt_br: ?string, en: ?string}>
     */
    private function mergeWithOriginalLines(array $originalLines, array $translatedLines): array
    {
        $indexedTranslated = [];

        foreach ($translatedLines as $line) {
            $index = $line['index'] ?? null;

            if (! is_int($index)) {
                continue;
            }

            $indexedTranslated[$index] = $line;
        }

        return array_values(array_map(function (array $line) use ($indexedTranslated): array {
            $translated = $indexedTranslated[$line['index']] ?? [];
            $sourceLang = $translated['source_lang'] ?? 'other';
            $ptBr = $translated['pt_br'] ?? null;
            $en = $translated['en'] ?? null;

            return [
                'index' => $line['index'],
                'timestamp' => $line['timestamp'],
                'text' => $line['text'],
                'source_lang' => is_string($sourceLang) ? $sourceLang : 'other',
                'pt_br' => is_string($ptBr) ? $ptBr : null,
                'en' => is_string($en) ? $en : null,
            ];
        }, $originalLines));
    }
}
