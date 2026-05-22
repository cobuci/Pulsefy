<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Services\Spotify\Contracts\SpotifyPlaybackProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class QueueController extends Controller
{
    public function __invoke(Request $request, SpotifyPlaybackProvider $playback): JsonResponse
    {
        return response()->json([
            'next_track' => $playback->nextQueuedTrack($request->user()),
        ]);
    }
}
