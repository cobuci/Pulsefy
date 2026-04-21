<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Jobs\RomanizeLyricsJob;
use App\Models\Lyric;
use App\Models\LyricPronunciation;
use App\Services\LyricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LyricsPronunciationController extends Controller
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
                'message' => 'Lyrics are not available for romanization.',
            ], 422);
        }

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
}
