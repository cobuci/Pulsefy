<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Player\Concerns\RespondsWithPlayerJson;
use App\Services\Spotify\Contracts\SpotifyPlaybackProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DevicesController extends Controller
{
    use RespondsWithPlayerJson;

    public function __construct(private readonly SpotifyPlaybackProvider $playback) {}

    public function __invoke(Request $request): JsonResponse
    {
        return $this->respondPayload([
            'devices' => $this->playback->devices($request->user()),
        ]);
    }
}
