<?php

namespace App\Services\Lyrics;

final class LrcLineParser
{
    /**
     * @return array<int, array{index: int, timestamp: ?string, text: string}>
     */
    public function parse(string $lyrics): array
    {
        $lines = preg_split('/\R/u', $lyrics) ?: [];

        return array_values(array_map(
            function (string $line, int $index): array {
                $trimmed = trim($line);
                $match = [];
                $hasTimestamp = preg_match('/^\[(\d{1,2}:\d{2}(?:\.\d{1,3})?)\]\s*(.*)$/', $trimmed, $match) === 1;

                if (! $hasTimestamp) {
                    return [
                        'index' => $index,
                        'timestamp' => null,
                        'text' => $trimmed,
                    ];
                }

                return [
                    'index' => $index,
                    'timestamp' => $match[1],
                    'text' => $match[2] ?? '',
                ];
            },
            $lines,
            array_keys($lines),
        ));
    }

    /**
     * @param  array<int, array{index: int, timestamp: ?string, text: string, source_lang?: string, pt_br?: ?string, en?: ?string}>  $lines
     */
    public function formatForLanguage(array $lines, string $language): string
    {
        $normalizedLanguage = $language === 'pt-BR' ? 'pt_br' : 'en';

        return implode("\n", array_map(function (array $line) use ($normalizedLanguage): string {
            $translatedText = $line[$normalizedLanguage] ?? null;
            $text = is_string($translatedText) && $translatedText !== ''
                ? $translatedText
                : ($line['text'] ?? '');

            if (($line['timestamp'] ?? null) !== null && $line['timestamp'] !== '') {
                return '['.$line['timestamp'].'] '.$text;
            }

            return $text;
        }, $lines));
    }
}
