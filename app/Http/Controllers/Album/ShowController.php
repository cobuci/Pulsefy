<?php

namespace App\Http\Controllers\Album;

use App\Http\Controllers\Controller;
use App\Jobs\HydrateAlbumPageDataJob;
use App\Services\Spotify\Contracts\SpotifyArtistProvider;
use App\Services\Spotify\Contracts\SpotifyInsightsProvider;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ShowController extends Controller
{
    public function __invoke(Request $request, SpotifyArtistProvider $artistService, SpotifyInsightsProvider $insights, string $albumId): Response
    {
        $user = $request->user();

        HydrateAlbumPageDataJob::dispatch($user->id, $albumId)
            ->onQueue('spotify-sync');

        return Inertia::render('Album/Show', [
            'albumId' => $albumId,
            'artistId' => $request->string('artistId')->toString() ?: null,
            'artistName' => $request->string('artistName')->toString() ?: null,
            'album' => Inertia::defer(fn () => $artistService->album($user, $albumId)),
            'tracks' => Inertia::defer(fn () => $artistService->albumTracks($user, $albumId)),
            'isFavorite' => Inertia::defer(fn () => $artistService->isAlbumSaved($user, $albumId)),
            'insights' => Inertia::defer(fn () => $insights->album($user, $albumId)),
        ]);
    }
}
