<?php

namespace App\Http\Controllers\Discovery;

use App\Http\Controllers\Controller;
use App\Models\DiscoveryLikedTrack;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class LikedController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Discovery/Liked', [
            'likedTracks' => Inertia::scroll(
                fn () => DiscoveryLikedTrack::query()
                    ->where('user_id', $request->user()->id)
                    ->latest('liked_at')
                    ->paginate(20)
                    ->through(fn (DiscoveryLikedTrack $track) => [
                        'id' => $track->id,
                        'spotify_id' => $track->spotify_id,
                        'name' => $track->name,
                        'artist_name' => $track->artist_name,
                        'album_name' => $track->album_name,
                        'image_url' => $track->image_url,
                        'liked_at' => $track->liked_at->toDateString(),
                        'liked_at_formatted' => $track->liked_at->format('M j, Y'),
                    ])
            ),
        ]);
    }
}
