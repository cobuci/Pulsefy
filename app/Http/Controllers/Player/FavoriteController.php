<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Services\Spotify\Contracts\SpotifyPlaybackProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FavoriteController extends Controller
{
    public function __invoke(Request $request, SpotifyPlaybackProvider $playback): JsonResponse
    {
        $trackId = $request->string('track_id')->toString();

        if ($trackId === '') {
            return response()->json(['ok' => false, 'message' => 'track_id is required'], 422);
        }

        $favorite = $request->boolean('favorite', true);

        $ok = $favorite
            ? $playback->saveTrack($request->user(), $trackId)
            : $playback->unsaveTrack($request->user(), $trackId);

        if (! $ok) {
            return response()->json([
                'ok' => false,
                'favorite' => $playback->isTrackSaved($request->user(), $trackId),
                'requires_reauth' => true,
                'message' => 'Missing Spotify permission. Reconnect Spotify and try again.',
            ]);
        }

        return response()->json([
            'ok' => true,
            'favorite' => $favorite,
        ]);
    }
}
