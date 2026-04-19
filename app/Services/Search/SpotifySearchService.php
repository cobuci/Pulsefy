<?php

namespace App\Services\Search;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Track;
use App\Models\User;
use App\Services\Spotify\Client\SpotifySearchClient;
use App\Services\Spotify\SpotifyTokenService;
use Illuminate\Support\Facades\Log;

final class SpotifySearchService
{
    private const int LOCAL_LIMIT = 6;

    private const int REMOTE_LIMIT = 6;

    public function __construct(
        private readonly SpotifyTokenService $tokenService,
    ) {}

    /**
     * @return array{artists: array<int, array<string, mixed>>, albums: array<int, array<string, mixed>>, tracks: array<int, array<string, mixed>>}
     */
    public function search(User $user, string $query): array
    {
        $trimmedQuery = trim($query);

        if ($trimmedQuery === '' || mb_strlen($trimmedQuery) < 2) {
            return [
                'artists' => [],
                'albums' => [],
                'tracks' => [],
            ];
        }

        $local = $this->localSearch($trimmedQuery);
        $remote = $this->remoteSearch($user, $trimmedQuery);

        return [
            'artists' => $this->mergeBySpotifyId($local['artists'], $remote['artists']),
            'albums' => $this->mergeBySpotifyId($local['albums'], $remote['albums']),
            'tracks' => $this->mergeBySpotifyId($local['tracks'], $remote['tracks']),
        ];
    }

    /**
     * @return array{artists: array<int, array<string, mixed>>, albums: array<int, array<string, mixed>>, tracks: array<int, array<string, mixed>>}
     */
    private function localSearch(string $query): array
    {
        return [
            'artists' => Artist::query()
                ->where('artist_name', 'like', "%{$query}%")
                ->orderByDesc('updated_at')
                ->limit(self::LOCAL_LIMIT)
                ->get()
                ->map(fn (Artist $artist): array => [
                    'id' => $artist->artist_id,
                    'type' => 'artist',
                    'title' => $artist->artist_name ?? 'Unknown Artist',
                    'subtitle' => collect($artist->genres)->take(2)->implode(' · '),
                    'image' => data_get($artist->images, '0.url'),
                    'href' => route('artists.show', ['artistId' => $artist->artist_id]),
                    'source' => 'local',
                ])
                ->values()
                ->all(),
            'albums' => Album::query()
                ->with('artists:id,artist_id,artist_name')
                ->where('name', 'like', "%{$query}%")
                ->orderByDesc('updated_at')
                ->limit(self::LOCAL_LIMIT)
                ->get()
                ->map(fn (Album $album): array => [
                    'id' => $album->spotify_id,
                    'type' => 'album',
                    'title' => $album->name,
                    'subtitle' => $album->artists->pluck('artist_name')->filter()->take(2)->implode(' · '),
                    'image' => data_get($album->images, '0.url'),
                    'href' => route('albums.show', ['albumId' => $album->spotify_id]),
                    'source' => 'local',
                ])
                ->values()
                ->all(),
            'tracks' => Track::query()
                ->with(['artists:id,artist_id,artist_name', 'album:id,spotify_id,name,images'])
                ->where('name', 'like', "%{$query}%")
                ->orderByDesc('updated_at')
                ->limit(self::LOCAL_LIMIT)
                ->get()
                ->map(fn (Track $track): array => [
                    'id' => $track->spotify_id,
                    'type' => 'track',
                    'title' => $track->name,
                    'subtitle' => $track->artists->pluck('artist_name')->filter()->take(2)->implode(' · '),
                    'image' => data_get($track->album?->images, '0.url'),
                    'href' => $track->album?->spotify_id
                        ? route('albums.show', ['albumId' => $track->album->spotify_id])
                        : route('recently-played'),
                    'source' => 'local',
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{artists: array<int, array<string, mixed>>, albums: array<int, array<string, mixed>>, tracks: array<int, array<string, mixed>>}
     */
    private function remoteSearch(User $user, string $query): array
    {
        try {
            $response = $this->spotifySearchClient($user)->search($query, self::REMOTE_LIMIT);

            if (! $response->successful()) {
                return [
                    'artists' => [],
                    'albums' => [],
                    'tracks' => [],
                ];
            }

            $remoteArtists = $response->json('artists.items', []);
            $remoteAlbums = $response->json('albums.items', []);
            $remoteTracks = $response->json('tracks.items', []);

            $this->linkExistingRelationsFromSearchPayload(
                artistsPayload: is_array($remoteArtists) ? $remoteArtists : [],
                albumsPayload: is_array($remoteAlbums) ? $remoteAlbums : [],
                tracksPayload: is_array($remoteTracks) ? $remoteTracks : [],
            );

            $remote = [
                'artists' => collect($remoteArtists)
                    ->map(fn (array $artist): array => [
                        'id' => (string) data_get($artist, 'id'),
                        'type' => 'artist',
                        'title' => (string) data_get($artist, 'name', 'Unknown Artist'),
                        'subtitle' => collect((array) data_get($artist, 'genres', []))->take(2)->implode(' · '),
                        'image' => data_get($artist, 'images.0.url'),
                        'href' => route('artists.show', ['artistId' => (string) data_get($artist, 'id')]),
                        'source' => 'spotify',
                    ])
                    ->filter(fn (array $artist): bool => $artist['id'] !== '')
                    ->values()
                    ->all(),
                'albums' => collect($remoteAlbums)
                    ->map(fn (array $album): array => [
                        'id' => (string) data_get($album, 'id'),
                        'type' => 'album',
                        'title' => (string) data_get($album, 'name', 'Unknown Album'),
                        'subtitle' => collect((array) data_get($album, 'artists', []))
                            ->pluck('name')
                            ->filter()
                            ->take(2)
                            ->implode(' · '),
                        'image' => data_get($album, 'images.0.url'),
                        'href' => route('albums.show', ['albumId' => (string) data_get($album, 'id')]),
                        'source' => 'spotify',
                    ])
                    ->filter(fn (array $album): bool => $album['id'] !== '')
                    ->values()
                    ->all(),
                'tracks' => collect($remoteTracks)
                    ->map(function (array $track): ?array {
                        $trackId = (string) data_get($track, 'id', '');
                        $albumId = (string) data_get($track, 'album.id', '');

                        if ($trackId === '' || $albumId === '') {
                            return null;
                        }

                        return [
                            'id' => $trackId,
                            'type' => 'track',
                            'title' => (string) data_get($track, 'name', 'Unknown Track'),
                            'subtitle' => collect((array) data_get($track, 'artists', []))
                                ->pluck('name')
                                ->filter()
                                ->take(2)
                                ->implode(' · '),
                            'image' => data_get($track, 'album.images.0.url'),
                            'href' => route('albums.show', ['albumId' => $albumId]),
                            'source' => 'spotify',
                        ];
                    })
                    ->filter(fn (?array $track): bool => $track !== null)
                    ->values()
                    ->all(),
            ];

            return $this->hydrateMissingTrackAlbums($remote);
        } catch (\Throwable $exception) {
            Log::channel('spotify')->warning('Spotify search failed', [
                'query' => $query,
                'error' => $exception->getMessage(),
            ]);

            return [
                'artists' => [],
                'albums' => [],
                'tracks' => [],
            ];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $artistsPayload
     * @param  array<int, array<string, mixed>>  $albumsPayload
     * @param  array<int, array<string, mixed>>  $tracksPayload
     */
    private function linkExistingRelationsFromSearchPayload(array $artistsPayload, array $albumsPayload, array $tracksPayload): void
    {
        $artistIds = collect($artistsPayload)
            ->pluck('id')
            ->merge(collect($albumsPayload)->flatMap(fn (array $album): array => collect((array) data_get($album, 'artists', []))->pluck('id')->all()))
            ->merge(collect($tracksPayload)->flatMap(fn (array $track): array => collect((array) data_get($track, 'artists', []))->pluck('id')->all()))
            ->filter(fn (mixed $artistId): bool => is_string($artistId) && $artistId !== '')
            ->unique()
            ->values();

        if ($artistIds->isEmpty()) {
            return;
        }

        $existingArtists = Artist::query()
            ->whereIn('artist_id', $artistIds->all())
            ->get()
            ->keyBy('artist_id');

        if ($existingArtists->isEmpty()) {
            return;
        }

        $albumIds = collect($albumsPayload)
            ->pluck('id')
            ->filter(fn (mixed $albumId): bool => is_string($albumId) && $albumId !== '')
            ->unique()
            ->values();

        $trackIds = collect($tracksPayload)
            ->pluck('id')
            ->filter(fn (mixed $trackId): bool => is_string($trackId) && $trackId !== '')
            ->unique()
            ->values();

        $existingAlbums = Album::query()
            ->whereIn('spotify_id', $albumIds->all())
            ->get()
            ->keyBy('spotify_id');

        $existingTracks = Track::query()
            ->whereIn('spotify_id', $trackIds->all())
            ->get()
            ->keyBy('spotify_id');

        collect($albumsPayload)->each(function (array $album) use ($existingArtists, $existingAlbums): void {
            $albumId = (string) data_get($album, 'id', '');

            if ($albumId === '') {
                return;
            }

            /** @var ?Album $existingAlbum */
            $existingAlbum = $existingAlbums->get($albumId);

            if (! $existingAlbum) {
                return;
            }

            $artistModelIds = collect((array) data_get($album, 'artists', []))
                ->map(fn (array $artist): ?int => $existingArtists->get((string) data_get($artist, 'id', ''))?->id)
                ->filter(fn (?int $artistModelId): bool => is_int($artistModelId))
                ->all();

            if ($artistModelIds === []) {
                return;
            }

            $existingAlbum->artists()->syncWithoutDetaching($artistModelIds);
        });

        collect($tracksPayload)->each(function (array $track) use ($existingArtists, $existingTracks): void {
            $trackId = (string) data_get($track, 'id', '');

            if ($trackId === '') {
                return;
            }

            /** @var ?Track $existingTrack */
            $existingTrack = $existingTracks->get($trackId);

            if (! $existingTrack) {
                return;
            }

            $artistModelIds = collect((array) data_get($track, 'artists', []))
                ->map(fn (array $artist): ?int => $existingArtists->get((string) data_get($artist, 'id', ''))?->id)
                ->filter(fn (?int $artistModelId): bool => is_int($artistModelId))
                ->all();

            if ($artistModelIds === []) {
                return;
            }

            $existingTrack->artists()->syncWithoutDetaching($artistModelIds);
        });
    }

    /**
     * @param  array{artists: array<int, array<string, mixed>>, albums: array<int, array<string, mixed>>, tracks: array<int, array<string, mixed>>}  $remote
     * @return array{artists: array<int, array<string, mixed>>, albums: array<int, array<string, mixed>>, tracks: array<int, array<string, mixed>>}
     */
    private function hydrateMissingTrackAlbums(array $remote): array
    {
        $albumIdsFromTracks = collect($remote['tracks'])
            ->map(fn (array $track): ?string => $this->albumIdFromAlbumHref((string) ($track['href'] ?? '')))
            ->filter(fn (?string $albumId): bool => is_string($albumId) && $albumId !== '')
            ->values();

        if ($albumIdsFromTracks->isEmpty()) {
            return $remote;
        }

        $knownAlbumIds = collect($remote['albums'])
            ->map(fn (array $album): string => (string) ($album['id'] ?? ''))
            ->filter(fn (string $id): bool => $id !== '');

        $missingAlbumIds = $albumIdsFromTracks
            ->diff($knownAlbumIds)
            ->unique()
            ->values();

        if ($missingAlbumIds->isEmpty()) {
            return $remote;
        }

        $hydratedAlbums = Album::query()
            ->with('artists:id,artist_id,artist_name')
            ->whereIn('spotify_id', $missingAlbumIds->all())
            ->get()
            ->map(fn (Album $album): array => [
                'id' => $album->spotify_id,
                'type' => 'album',
                'title' => $album->name,
                'subtitle' => $album->artists->pluck('artist_name')->filter()->take(2)->implode(' · '),
                'image' => data_get($album->images, '0.url'),
                'href' => route('albums.show', ['albumId' => $album->spotify_id]),
                'source' => 'local-hydrated',
            ])
            ->values()
            ->all();

        $remote['albums'] = $this->mergeBySpotifyId($remote['albums'], $hydratedAlbums);

        return $remote;
    }

    private function albumIdFromAlbumHref(string $href): ?string
    {
        $path = parse_url($href, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        $segments = collect(explode('/', trim($path, '/')));
        $albumIndex = $segments->search('albums');

        if (! is_int($albumIndex)) {
            return null;
        }

        $albumId = $segments->get($albumIndex + 1);

        if (! is_string($albumId) || $albumId === '') {
            return null;
        }

        return $albumId;
    }

    /**
     * @param  array<int, array<string, mixed>>  $localItems
     * @param  array<int, array<string, mixed>>  $remoteItems
     * @return array<int, array<string, mixed>>
     */
    private function mergeBySpotifyId(array $localItems, array $remoteItems): array
    {
        $merged = collect($localItems)
            ->keyBy('id');

        collect($remoteItems)
            ->each(function (array $item) use ($merged): void {
                $id = (string) data_get($item, 'id', '');

                if ($id === '') {
                    return;
                }

                if (! $merged->has($id)) {
                    $merged->put($id, $item);

                    return;
                }

                /** @var array<string, mixed> $existing */
                $existing = (array) $merged->get($id);

                if (($existing['image'] ?? null) === null && ($item['image'] ?? null) !== null) {
                    $existing['image'] = $item['image'];
                }

                if (($existing['subtitle'] ?? '') === '' && ($item['subtitle'] ?? '') !== '') {
                    $existing['subtitle'] = $item['subtitle'];
                }

                $merged->put($id, $existing);
            });

        return $merged
            ->values()
            ->take(self::LOCAL_LIMIT + self::REMOTE_LIMIT)
            ->all();
    }

    private function spotifySearchClient(User $user): SpotifySearchClient
    {
        return new SpotifySearchClient($this->tokenService->ensureFreshToken($user));
    }
}
