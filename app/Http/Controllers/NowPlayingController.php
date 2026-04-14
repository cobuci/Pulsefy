<?php

namespace App\Http\Controllers;

use App\Services\Spotify\SpotifyDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NowPlayingController extends Controller
{
    public function __invoke(Request $request, SpotifyDataService $spotify): JsonResponse
    {
        $data = $spotify->currentlyPlaying($request->user());

        if ($data === null) {
            return response()->json(null, 204);
        }

        return response()->json($data);
    }
}
