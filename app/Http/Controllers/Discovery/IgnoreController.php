<?php

namespace App\Http\Controllers\Discovery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Discovery\IgnoreTrackRequest;
use App\Services\Discovery\DiscoveryLikeService;
use Illuminate\Http\JsonResponse;

final class IgnoreController extends Controller
{
    public function __invoke(IgnoreTrackRequest $request, DiscoveryLikeService $likes): JsonResponse
    {
        $likes->ignore($request->user(), $request->validated('spotify_id'));

        return response()->json(['ok' => true, 'ignored' => true]);
    }
}
