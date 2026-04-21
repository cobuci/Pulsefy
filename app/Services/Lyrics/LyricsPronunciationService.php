<?php

namespace App\Services\Lyrics;

use App\Ai\Agents\LyricsPronunciationAgent;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class LyricsPronunciationService
{
    public function __construct(
        private readonly LrcLineParser $lineParser,
    ) {}

    /**
     * @return array{provider: string, model: string, lines: array<int, array{index: int, timestamp: ?string, pt_br: ?string, en: ?string}>}
     */
    public function romanize(
        string $artist,
        string $trackName,
        string $lyrics,
    ): array {
        $lines = $this->lineParser->parse($lyrics);

        if ($lines === []) {
            throw new RuntimeException('No lyric lines available for romanization.');
        }

        $prompt = $this->buildPrompt($artist, $trackName, $lines);
        $model = (string) config('services.lyrics_romanization.model', 'gemini-3.1-flash-lite-preview');
        $provider = (string) config('services.lyrics_romanization.provider', 'gemini');

        $response = (new LyricsPronunciationAgent)->prompt($prompt, model: $model);
        $romanizedLines = $response['lines'] ?? [];

        return [
            'provider' => $provider,
            'model' => $model,
            'lines' => $this->mergeWithOriginalLines($lines, is_array($romanizedLines) ? $romanizedLines : []),
        ];
    }

    /**
     * @param  array<int, array{index: int, timestamp: ?string, text: string}>  $originalLines
     */
    private function buildPrompt(string $artist, string $trackName, array $originalLines): string
    {
        $template = File::get(resource_path('prompts/lyrics/romanization.user.md'));

        return strtr($template, [
            '{{artist}}' => $artist,
            '{{track}}' => $trackName,
            '{{lines_json}}' => json_encode($originalLines, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]',
        ]);
    }

    /**
     * @param  array<int, array{index: int, timestamp: ?string, text: string}>  $originalLines
     * @param  array<int, array<string, mixed>>  $romanizedLines
     * @return array<int, array{index: int, timestamp: ?string, pt_br: ?string, en: ?string}>
     */
    private function mergeWithOriginalLines(array $originalLines, array $romanizedLines): array
    {
        $indexed = [];

        foreach ($romanizedLines as $line) {
            $index = $line['index'] ?? null;

            if (! is_int($index)) {
                continue;
            }

            $indexed[$index] = $line;
        }

        return array_values(array_map(function (array $line) use ($indexed): array {
            $romanized = $indexed[$line['index']] ?? [];
            $ptBr = $romanized['pt_br'] ?? null;
            $en = $romanized['en'] ?? null;

            return [
                'index' => $line['index'],
                'timestamp' => $line['timestamp'],
                'pt_br' => is_string($ptBr) ? $ptBr : null,
                'en' => is_string($en) ? $en : null,
            ];
        }, $originalLines));
    }
}
