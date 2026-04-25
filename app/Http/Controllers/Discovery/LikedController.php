<?php

namespace App\Http\Controllers\Discovery;

use App\Http\Controllers\Controller;
use App\Models\DiscoveryLikedTrack;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class LikedController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $total = DiscoveryLikedTrack::query()
            ->where('user_id', $user->id)
            ->count();

        return Inertia::render('Discovery/Liked', [
            'total' => $total,
            'likedTracks' => Inertia::scroll(
                /** @phpstan-ignore return.type */
                fn () => DiscoveryLikedTrack::query()
                    ->where('user_id', $user->id)
                    ->with('track.artists')
                    ->latest('liked_at')
                    ->paginate(50)
                    ->through(fn (DiscoveryLikedTrack $liked) => [
                        'id' => $liked->id,
                        'spotify_id' => $liked->track->spotify_id,
                        'uri' => 'spotify:track:'.$liked->track->spotify_id,
                        'name' => $liked->track->name,
                        'duration_ms' => $liked->track->duration_ms,
                        'image_url' => $liked->track->image_url,
                        /** @phpstan-ignore nullsafe.neverNull */
                        'artists' => $liked->track->artists->map(fn ($a) => [
                            'id' => $a->artist_id,
                            'name' => $a->artist_name,
                        ])->values(),
                        'liked_at' => $liked->liked_at->toDateString(),
                    ])
            ),
        ]);
    }
}
