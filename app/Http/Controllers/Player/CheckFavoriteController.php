<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Services\Spotify\Contracts\SpotifyPlaybackProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CheckFavoriteController extends Controller
{
    public function __invoke(Request $request, SpotifyPlaybackProvider $playback): JsonResponse
    {
        $trackId = $request->string('track_id')->toString();

        if ($trackId === '') {
            return response()->json(['ok' => false, 'message' => 'track_id is required'], 422);
        }

        $isSaved = $playback->isTrackSaved($request->user(), $trackId);

        return response()->json(['ok' => true, 'saved' => $isSaved]);
    }
}
