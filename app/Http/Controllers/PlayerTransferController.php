<?php

namespace App\Http\Controllers;

use App\Services\Spotify\SpotifyDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlayerTransferController extends Controller
{
    public function __construct(private readonly SpotifyDataService $spotify) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string'],
            'play' => ['nullable', 'boolean'],
        ]);

        $success = $this->spotify->transferPlayback(
            $request->user(),
            $validated['device_id'],
            (bool) ($validated['play'] ?? true),
        );

        return response()->json(
            ['ok' => $success],
            $success ? 200 : 403,
        );
    }
}
