<?php

namespace App\Http\Controllers\Discovery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Discovery\LikeTrackRequest;
use App\Services\Discovery\DiscoveryLikeService;
use Illuminate\Http\JsonResponse;

final class LikeController extends Controller
{
    public function __invoke(LikeTrackRequest $request, DiscoveryLikeService $likes): JsonResponse
    {
        $likes->like($request->user(), $request->validated());

        return response()->json(['ok' => true, 'liked' => true]);
    }
}
