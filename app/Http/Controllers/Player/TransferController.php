<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Player\Concerns\RespondsWithPlayerJson;
use App\Services\Spotify\Contracts\SpotifyPlaybackProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TransferController extends Controller
{
    use RespondsWithPlayerJson;

    public function __construct(private readonly SpotifyPlaybackProvider $playback) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string'],
            'play' => ['nullable', 'boolean'],
        ]);

        $success = $this->playback->transferPlayback(
            $request->user(),
            $validated['device_id'],
            (bool) ($validated['play'] ?? true),
        );

        return $this->respondOperation($success, 403);
    }
}
