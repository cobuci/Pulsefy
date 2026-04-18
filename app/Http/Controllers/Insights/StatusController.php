<?php

namespace App\Http\Controllers\Insights;

use App\Http\Controllers\Controller;
use App\Services\Spotify\Sync\SpotifySyncStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StatusController extends Controller
{
    public function __invoke(Request $request, SpotifySyncStatusService $status): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'status' => $status->forUser($user),
        ]);
    }
}
