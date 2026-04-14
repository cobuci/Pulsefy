<?php

namespace App\Http\Controllers;

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

        return response()->json(
            $lyrics->getLyrics(
                $validated['track_id'],
                $validated['artist'],
                $validated['track_name'],
                $validated['album_name'] ?? null,
                isset($validated['duration']) ? (float) $validated['duration'] : null,
                $request->boolean('force_refresh'),
            )
        );
    }
}
