<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Jobs\RomanizeLyricsJob;
use App\Jobs\TranslateLyricsJob;
use App\Models\Lyric;
use App\Models\LyricPronunciation;
use App\Models\LyricTranslation;
use App\Models\User;
use App\Services\LyricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LyricsController extends Controller
{
    public function show(Request $request, LyricsService $lyrics): JsonResponse
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

    public function translate(Request $request, LyricsService $lyricsService): JsonResponse
    {
        $validated = $request->validate([
            'track_id' => ['required', 'string'],
            'artist' => ['required', 'string'],
            'track_name' => ['required', 'string'],
            'album_name' => ['nullable', 'string'],
            'duration' => ['nullable', 'numeric'],
            'force_refresh' => ['sometimes', 'boolean'],
        ]);

        $lyricsService->getLyrics(
            $validated['track_id'],
            $validated['artist'],
            $validated['track_name'],
            $validated['album_name'] ?? null,
            isset($validated['duration']) ? (float) $validated['duration'] : null,
            $request->boolean('force_refresh'),
        );

        $lyric = Lyric::query()->where('track_id', $validated['track_id'])->first();

        if (! $lyric || ($lyric->synced_lyrics === null && $lyric->plain_lyrics === null)) {
            return response()->json([
                'ok' => false,
                'message' => 'Lyrics are not available for translation.',
            ], 422);
        }

        /** @var User $user */
        $user = $request->user();

        $existing = LyricTranslation::query()
            ->where('user_id', $user->id)
            ->where('lyric_id', $lyric->id)
            ->first();

        if (
            $existing !== null
            && $existing->status === LyricTranslation::STATUS_PROCESSING
            && $existing->started_at !== null
            && $existing->started_at->copy()->addMinutes(3)->isPast()
        ) {
            $existing->update([
                'status' => LyricTranslation::STATUS_FAILED,
                'completed_at' => now(),
                'error_message' => 'Previous translation attempt timed out. Please try again.',
            ]);
        }

        if ($existing !== null && in_array($existing->status, [
            LyricTranslation::STATUS_QUEUED,
            LyricTranslation::STATUS_PROCESSING,
            LyricTranslation::STATUS_READY,
        ], true)) {
            return response()->json([
                'ok' => true,
                'track_id' => $existing->track_id,
                'status' => $existing->status,
            ], 202);
        }

        $translation = LyricTranslation::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'lyric_id' => $lyric->id,
            ],
            [
                'track_id' => $validated['track_id'],
                'status' => LyricTranslation::STATUS_QUEUED,
                'translated_lines' => null,
                'provider' => null,
                'model' => null,
                'requested_at' => now(),
                'started_at' => null,
                'completed_at' => null,
                'error_message' => null,
            ],
        );

        TranslateLyricsJob::dispatch($translation->id);

        return response()->json([
            'ok' => true,
            'track_id' => $translation->track_id,
            'status' => $translation->status,
        ], 202);
    }

    public function romanize(Request $request, LyricsService $lyricsService): JsonResponse
    {
        $validated = $request->validate([
            'track_id' => ['required', 'string'],
            'artist' => ['required', 'string'],
            'track_name' => ['required', 'string'],
            'album_name' => ['nullable', 'string'],
            'duration' => ['nullable', 'numeric'],
            'force_refresh' => ['sometimes', 'boolean'],
        ]);

        $lyricsService->getLyrics(
            $validated['track_id'],
            $validated['artist'],
            $validated['track_name'],
            $validated['album_name'] ?? null,
            isset($validated['duration']) ? (float) $validated['duration'] : null,
            $request->boolean('force_refresh'),
        );

        $lyric = Lyric::query()->where('track_id', $validated['track_id'])->first();

        if (! $lyric || ($lyric->synced_lyrics === null && $lyric->plain_lyrics === null)) {
            return response()->json([
                'ok' => false,
                'message' => 'Lyrics are not available for romanization.',
            ], 422);
        }

        /** @var User $user */
        $user = $request->user();

        $existing = LyricPronunciation::query()
            ->where('user_id', $user->id)
            ->where('lyric_id', $lyric->id)
            ->first();

        if (
            $existing !== null
            && $existing->status === LyricPronunciation::STATUS_PROCESSING
            && $existing->started_at !== null
            && $existing->started_at->copy()->addMinutes(3)->isPast()
        ) {
            $existing->update([
                'status' => LyricPronunciation::STATUS_FAILED,
                'completed_at' => now(),
                'error_message' => 'Previous romanization attempt timed out. Please try again.',
            ]);
        }

        if (
            $existing !== null
            && in_array($existing->status, [LyricPronunciation::STATUS_QUEUED, LyricPronunciation::STATUS_PROCESSING, LyricPronunciation::STATUS_READY], true)
        ) {
            return response()->json([
                'ok' => true,
                'track_id' => $existing->track_id,
                'status' => $existing->status,
            ], 202);
        }

        $pronunciation = LyricPronunciation::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'lyric_id' => $lyric->id,
            ],
            [
                'track_id' => $validated['track_id'],
                'status' => LyricPronunciation::STATUS_QUEUED,
                'romanized_lines' => null,
                'provider' => null,
                'model' => null,
                'requested_at' => now(),
                'started_at' => null,
                'completed_at' => null,
                'error_message' => null,
            ],
        );

        RomanizeLyricsJob::dispatch($pronunciation->id);

        return response()->json([
            'ok' => true,
            'track_id' => $pronunciation->track_id,
            'status' => $pronunciation->status,
        ], 202);
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
