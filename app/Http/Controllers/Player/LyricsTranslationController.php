<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Jobs\TranslateLyricsJob;
use App\Models\Lyric;
use App\Models\LyricTranslation;
use App\Services\LyricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LyricsTranslationController extends Controller
{
    public function __invoke(Request $request, LyricsService $lyricsService): JsonResponse
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
}
