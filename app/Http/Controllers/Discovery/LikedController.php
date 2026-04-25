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

        return Inertia::render('Discovery/Liked', [
            'likedTracks' => Inertia::scroll(
                /** @phpstan-ignore return.type */
                fn () => DiscoveryLikedTrack::query()
                    ->where('user_id', $user->id)
                    ->with('track.artists', 'track.album')
                    ->latest('liked_at')
                    ->paginate(20)
                    ->through(fn (DiscoveryLikedTrack $liked) => [
                        'id' => $liked->id,
                        'spotify_id' => $liked->track->spotify_id,
                        'name' => $liked->track->name,
                        /** @phpstan-ignore nullsafe.neverNull */
                        'artist_name' => $liked->track->artists->first()?->artist_name ?? '',
                        /** @phpstan-ignore nullsafe.neverNull */
                        'album_name' => $liked->track->album?->name ?? '',
                        'image_url' => $liked->track->image_url,
                        'liked_at' => $liked->liked_at->toDateString(),
                        'liked_at_formatted' => $liked->liked_at->format('M j, Y'),
                    ])
            ),
        ]);
    }
}
