<?php

namespace App\Http\Controllers\Artist;

use App\Http\Controllers\Controller;
use App\Jobs\HydrateArtistPageDataJob;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Track;
use App\Services\Spotify\Contracts\SpotifyArtistProvider;
use App\Services\Spotify\Contracts\SpotifyInsightsProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

final class ShowController extends Controller
{
    public function __invoke(Request $request, SpotifyArtistProvider $artistService, SpotifyInsightsProvider $insights, string $artistId): Response
    {
        $user = $request->user();

        if (! Cache::has('spotify:artist:hydrate:'.$artistId)) {
            Cache::put('spotify:artist:hydrate:'.$artistId, true, now()->addSeconds(30));

            HydrateArtistPageDataJob::dispatch($user->id, $artistId)
                ->onQueue('spotify-sync');
        }

        $artistModel = Artist::query()
            /** @var Artist|null $artistModel */
            ->where('artist_id', $artistId)
            ->with(['albums', 'tracks.album', 'tracks.artists'])
            ->first();

        $cachedArtist = $artistModel
            ? [
                'id' => $artistModel->artist_id,
                'name' => $artistModel->artist_name,
                'images' => $artistModel->images ?? $artistModel->tracks()
                    ->get()
                    ->map(fn (Track $track): ?array => $track->album?->images)
                    ->filter(fn (?array $images): bool => is_array($images) && $images !== [])
                    ->first() ?? [],
                'genres' => $artistModel->genres,
                'popularity' => $artistModel->popularity ?? 0,
                'uri' => $artistModel->uri ?? 'spotify:artist:'.$artistModel->artist_id,
                'external_urls' => $artistModel->external_urls ?? [
                    'spotify' => 'https://open.spotify.com/artist/'.$artistModel->artist_id,
                ],
            ]
            : null;

        $cachedTracks = $artistModel
            ? $artistModel->tracks()
                ->with(['album', 'artists'])
                ->latest('artist_track.updated_at')
                ->take(10)
                ->get()
                ->map(fn (Track $track): array => [
                    'id' => $track->spotify_id,
                    'uri' => 'spotify:track:'.$track->spotify_id,
                    'name' => $track->name,
                    'artists' => $track->artists
                        ->map(fn (Artist $artist): array => [
                            'id' => $artist->artist_id,
                            'name' => $artist->artist_name,
                            'external_urls' => [
                                'spotify' => 'https://open.spotify.com/artist/'.$artist->artist_id,
                            ],
                        ])
                        ->values()
                        ->all(),
                    'album' => [
                        'id' => $track->album?->spotify_id,
                        'name' => $track->album?->name,
                        'images' => $track->album?->images ?? [],
                        'release_date' => $track->album?->release_date ?? '',
                        'external_urls' => [
                            'spotify' => $track->album?->spotify_id
                                ? 'https://open.spotify.com/album/'.$track->album->spotify_id
                                : '',
                        ],
                    ],
                    'duration_ms' => $track->duration_ms,
                    'popularity' => 0,
                    'preview_url' => null,
                    'external_urls' => [
                        'spotify' => 'https://open.spotify.com/track/'.$track->spotify_id,
                    ],
                ])
                ->values()
                ->all()
            : [];

        $cachedAlbums = $artistModel
            ? $artistModel->albums
                ->map(fn (Album $album): array => [
                    'id' => $album->spotify_id,
                    'name' => $album->name,
                    'images' => $album->images ?? [],
                    'release_date' => $album->release_date,
                    'album_type' => $album->album_type,
                    'album_group' => $album->album_type,
                    'total_tracks' => $album->total_tracks,
                    'external_urls' => [
                        'spotify' => 'https://open.spotify.com/album/'.$album->spotify_id,
                    ],
                ])
                ->values()
                ->all()
            : [];

        return Inertia::render('Artist/Show', [
            'artistId' => $artistId,
            'artist' => Inertia::defer(fn () => $artistService->artist($user, $artistId) ?? $cachedArtist),
            'topTracks' => Inertia::defer(fn () => $artistService->topTracks($user, $artistId) ?: $cachedTracks),
            'albums' => Inertia::defer(fn () => $artistService->albums($user, $artistId) ?: $cachedAlbums),
            'isFavorite' => Inertia::defer(fn () => $artistService->isArtistFollowed($user, $artistId)),
            'insights' => Inertia::defer(fn () => $insights->artist($user, $artistId)),
        ]);
    }
}
