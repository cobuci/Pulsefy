<?php

namespace App\Http\Controllers;

use App\Services\Spotify\SpotifyService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class NowPlayingController extends Controller
{
    public function __invoke(Request $request, SpotifyService $spotify): SymfonyResponse
    {
        $data = $spotify->currentlyPlaying($request->user());

        if ($data === null) {
            return response()->noContent();
        }

        return response()->json($data);
    }
}
