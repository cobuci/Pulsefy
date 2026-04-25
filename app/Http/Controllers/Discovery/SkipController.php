<?php

namespace App\Http\Controllers\Discovery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Discovery\SkipTrackRequest;
use App\Services\Discovery\DiscoveryLikeService;
use Illuminate\Http\JsonResponse;

final class SkipController extends Controller
{
    public function __invoke(SkipTrackRequest $request, DiscoveryLikeService $likes): JsonResponse
    {
        $likes->skip($request->user(), $request->validated('spotify_id'));

        return response()->json(['ok' => true, 'skipped' => true]);
    }
}
