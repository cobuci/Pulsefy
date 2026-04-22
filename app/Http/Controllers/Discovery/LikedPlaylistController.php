<?php

namespace App\Http\Controllers\Discovery;

use App\Http\Controllers\Controller;
use App\Models\DiscoveryLikedTrack;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LikedPlaylistController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 50);

        $paginator = DiscoveryLikedTrack::query()
            ->where('user_id', $request->user()->id)
            ->latest('liked_at')
            ->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
