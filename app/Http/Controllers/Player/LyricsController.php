<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Models\LyricPronunciation;
use App\Models\LyricTranslation;
use App\Services\LyricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LyricsController extends Controller
{
    public function __invoke(Request $request, LyricsService $lyrics): JsonResponse
    {
        $validated = $request->validate([
            'track_id' => ['required', 'string'],
            'artist' => ['required', 'string'],
            'track_name' => ['required', 'string'],
            'album_name' => ['nullable', 'string'],
            'duration' => ['nullable', 'numeric'],
            'force_refresh' => ['sometimes', 'boolean'],
        ]);

        $trackId = $validated['track_id'];

        $payload = $lyrics->getLyrics(
            $trackId,
            $validated['artist'],
            $validated['track_name'],
            $validated['album_name'] ?? null,
            isset($validated['duration']) ? (float) $validated['duration'] : null,
            $request->boolean('force_refresh'),
        );

        return response()->json(
            $this->withPronunciationData(
                $request,
                $trackId,
                $this->withTranslationData($request, $trackId, $payload),
            )
        );
    }

    /**
     * @param  array{track_id: string, type: 'synced'|'plain'|'none', lyrics: ?string, synced: bool}  $payload
     * @return array{track_id: string, type: 'synced'|'plain'|'none', lyrics: ?string, synced: bool, translation: array{status: ?string, translated_lines: ?array, provider: ?string, model: ?string, error_message: ?string}}
     */
    private function withTranslationData(Request $request, string $trackId, array $payload): array
    {
        $user = $request->user();

        if (! $user) {
            return $payload + [
                'translation' => [
                    'status' => null,
                    'translated_lines' => null,
                    'provider' => null,
                    'model' => null,
                    'error_message' => null,
                ],
            ];
        }

        $translation = LyricTranslation::query()
            ->where('user_id', $user->id)
            ->where('track_id', $trackId)
            ->latest('id')
            ->first();

        return $payload + [
            'translation' => [
                'status' => $translation?->status,
                'translated_lines' => $translation?->translated_lines,
                'provider' => $translation?->provider,
                'model' => $translation?->model,
                'error_message' => $translation?->error_message,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withPronunciationData(Request $request, string $trackId, array $payload): array
    {
        $user = $request->user();

        if (! $user) {
            return $payload + [
                'romanization' => [
                    'status' => null,
                    'romanized_lines' => null,
                    'provider' => null,
                    'model' => null,
                    'error_message' => null,
                ],
            ];
        }

        $pronunciation = LyricPronunciation::query()
            ->where('user_id', $user->id)
            ->where('track_id', $trackId)
            ->latest('id')
            ->first();

        return $payload + [
            'romanization' => [
                'status' => $pronunciation?->status,
                'romanized_lines' => $pronunciation?->romanized_lines,
                'provider' => $pronunciation?->provider,
                'model' => $pronunciation?->model,
                'error_message' => $pronunciation?->error_message,
            ],
        ];
    }
}
