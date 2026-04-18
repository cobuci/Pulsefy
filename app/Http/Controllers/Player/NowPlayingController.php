<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Services\Spotify\Contracts\SpotifyPlaybackProvider;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class NowPlayingController extends Controller
{
    public function __invoke(Request $request, SpotifyPlaybackProvider $playback): SymfonyResponse
    {
        $data = $playback->currentlyPlaying($request->user());

        if ($data === null) {
            return response()->noContent();
        }

        return response()->json($data);
    }
}
