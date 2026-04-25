<?php

namespace App\Http\Controllers\Discovery;

use App\Http\Controllers\Controller;
use App\Models\DiscoveryLikedTrack;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LikedPlaylistController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $perPage = min((int) $request->query('per_page', 20), 50);

        $paginator = DiscoveryLikedTrack::query()
            ->where('user_id', $user->id)
            ->with('track.artists', 'track.album')
            ->latest('liked_at')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn (DiscoveryLikedTrack $liked) => [
                'id' => $liked->id,
                'spotify_id' => $liked->track->spotify_id,
                'name' => $liked->track->name,
                /** @phpstan-ignore nullsafe.neverNull */
                'artist_name' => $liked->track->artists->first()?->artist_name ?? '',
                /** @phpstan-ignore nullsafe.neverNull */
                'album_name' => $liked->track->album?->name ?? '',
                'image_url' => $liked->track->image_url,
                'liked_at' => $liked->liked_at->toDateString(),
            ])->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
