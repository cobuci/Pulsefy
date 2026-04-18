<?php

namespace App\Http\Controllers\Artist;

use App\Http\Controllers\Controller;
use App\Services\Spotify\Contracts\SpotifyArtistProvider;
use App\Services\Spotify\Contracts\SpotifyInsightsProvider;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ShowController extends Controller
{
    public function __invoke(Request $request, SpotifyArtistProvider $artistService, SpotifyInsightsProvider $insights, string $artistId): Response
    {
        $user = $request->user();

        return Inertia::render('Artist/Show', [
            'artistId' => $artistId,
            'artist' => Inertia::defer(fn () => $artistService->artist($user, $artistId)),
            'topTracks' => Inertia::defer(fn () => $artistService->topTracks($user, $artistId)),
            'albums' => Inertia::defer(fn () => $artistService->albums($user, $artistId)),
            'insights' => Inertia::defer(fn () => $insights->artist($user, $artistId)),
        ]);
    }
}
