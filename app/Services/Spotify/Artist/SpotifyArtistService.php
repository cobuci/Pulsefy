<?php

namespace App\Services\Spotify\Artist;

use App\Models\Album;
use App\Models\Artist;
use App\Models\SpotifyStat;
use App\Models\Track;
use App\Models\User;
use App\Services\Spotify\Client\SpotifyArtistClient;
use App\Services\Spotify\Concerns\CachesStats;
use App\Services\Spotify\Contracts\SpotifyArtistProvider;
use App\Services\Spotify\SpotifyTokenService;
use Closure;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * @phpstan-type SpotifyPayload array<string, mixed>
 * @phpstan-type SpotifyPayloadList array<int, array<string, mixed>>
 */
final readonly class SpotifyArtistService implements SpotifyArtistProvider
{
    use CachesStats;

    private const array FALLBACK_STATUSES = [403, 429];

    private const array EMPTY_PAYLOAD_STATUSES = [401, 403, 404];

    private const int ARTIST_FETCH_COOLDOWN_SECONDS = 45;

    public function __construct(
        private SpotifyTokenService $tokenService,
    ) {}

    public function artist(User $user, string $artistId): ?array
    {
        $dbArtist = $this->artistFromDatabase($artistId);

        if ($dbArtist !== null && $this->isArtistInCooldown($artistId)) {
            return $dbArtist;
        }

        $payload = $this->cached($user, 'artist_profile', 'v2:'.$artistId, function () use ($user, $artistId) {
            if ($this->isArtistInCooldown($artistId)) {
                return [];
            }

            return $this->fetchNullablePayload(
                user: $user,
                operation: 'artist',
                appRequest: fn (SpotifyArtistClient $client): Response => $client->artist($artistId),
                userRequest: fn (SpotifyArtistClient $client): Response => $client->artist($artistId),
                preferUser: true,
            );
        });

        if ($payload === []) {
            if ($dbArtist !== null) {
                return $dbArtist;
            }

            $snapshotArtist = $this->artistFromTopItemsSnapshot($user, $artistId);

            if ($snapshotArtist !== null) {
                return $snapshotArtist;
            }
        }

        return $payload === [] ? null : $payload;
    }

    private function artistFromTopItemsSnapshot(User $user, string $artistId): ?array
    {
        $snapshot = SpotifyStat::query()
            ->where('user_id', $user->id)
            ->where('type', 'top_items_snapshot')
            ->where('time_range', 'v2')
            ->latest('id')
            ->first();

        if ($snapshot === null) {
            return null;
        }

        $artists = collect((array) $snapshot->payload)
            ->flatMap(fn (array $range): array => (array) data_get($range, 'artists', []));

        $artist = $artists->first(
            fn (array $item): bool => (string) data_get($item, 'id') === $artistId,
        );

        if (! is_array($artist)) {
            return null;
        }

        return $artist;
    }

    private function artistFromDatabase(string $artistId): ?array
    {
        $artist = Artist::query()
            ->where('artist_id', $artistId)
            ->with('tracks.album')
            ->first();

        if (! $artist) {
            return null;
        }

        $images = [];

        $firstTrackWithAlbumArt = $artist->tracks
            ->first(fn (Track $track): bool => is_array($track->album?->images) && $track->album->images !== []);

        if ($firstTrackWithAlbumArt?->album?->images) {
            $images = $firstTrackWithAlbumArt->album->images;
        }

        return [
            'id' => $artist->artist_id,
            'name' => $artist->artist_name,
            'images' => $artist->images ?? $images,
            'genres' => $artist->genres,
            'popularity' => $artist->popularity ?? 0,
            'uri' => $artist->uri ?? 'spotify:artist:'.$artist->artist_id,
            'external_urls' => $artist->external_urls ?? [
                'spotify' => 'https://open.spotify.com/artist/'.$artist->artist_id,
            ],
        ];
    }

    public function topTracks(User $user, string $artistId): array
    {
        return $this->cached($user, 'artist_top_tracks', 'v5:'.$artistId, function () use ($user, $artistId) {
            $dbTracks = $this->topTracksFromDatabase($artistId);

            if ($dbTracks !== []) {
                return $dbTracks;
            }

            if ($this->isArtistInCooldown($artistId)) {
                return [];
            }

            return $this->searchTopTracksFallback($user, $artistId);
        });
    }

    /**
     * @return SpotifyPayloadList
     */
    private function searchTopTracksFallback(User $user, string $artistId): array
    {
        try {
            $artistName = (string) data_get($this->artistFromDatabase($artistId), 'name', '');

            if ($artistName === '') {
                $snapshotArtist = $this->artistFromTopItemsSnapshot($user, $artistId);
                $artistName = (string) data_get($snapshotArtist, 'name', '');
            }

            if ($artistName === '') {
                $artistResponse = $this->requestWithFallback(
                    user: $user,
                    appRequest: fn (SpotifyArtistClient $client): Response => $client->artist($artistId),
                    userRequest: fn (SpotifyArtistClient $client): Response => $client->artist($artistId),
                    preferUser: true,
                );

                if (in_array($artistResponse->status(), self::EMPTY_PAYLOAD_STATUSES, true)) {
                    return [];
                }

                if ($artistResponse->status() === 429) {
                    $this->activateArtistCooldown($artistId);

                    return [];
                }

                $artistName = (string) $artistResponse->throw()->json('name', '');
            }

            if ($artistName === '') {
                return [];
            }

            $tracksResponse = $this->requestWithFallback(
                user: $user,
                appRequest: fn (SpotifyArtistClient $client): Response => $client->searchTracksByArtist($artistName),
                userRequest: fn (SpotifyArtistClient $client): Response => $client->searchTracksByArtist($artistName),
                preferUser: true,
            );

            if (in_array($tracksResponse->status(), self::EMPTY_PAYLOAD_STATUSES, true)) {
                return [];
            }

            if ($tracksResponse->status() === 429) {
                $this->activateArtistCooldown($artistId);

                return [];
            }

            /** @var SpotifyPayloadList $tracks */
            $tracks = $tracksResponse->throw()->json('tracks.items', []);

            return collect($tracks)
                ->filter(function (array $track) use ($artistId): bool {
                    $artists = data_get($track, 'artists', []);

                    return collect($artists)
                        ->contains(fn (array $artist): bool => (string) data_get($artist, 'id', '') === $artistId);
                })
                ->take(10)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::channel('spotify')->warning('Spotify topTracks fallback failed', ['artist_id' => $artistId, 'error' => $e->getMessage()]);
            $this->activateArtistCooldown($artistId);

            return [];
        }
    }

    public function albums(User $user, string $artistId): array
    {
        return $this->cached($user, 'artist_albums', 'v2:'.$artistId, function () use ($user, $artistId) {
            $dbAlbums = $this->albumsFromDatabase($artistId);

            if ($dbAlbums !== []) {
                return $dbAlbums;
            }

            if ($this->isArtistInCooldown($artistId)) {
                return [];
            }

            try {
                $albums = [];
                $limit = 10;

                for ($offset = 0; $offset < 50; $offset += $limit) {
                    $response = $this->requestWithFallback(
                        user: $user,
                        appRequest: fn (SpotifyArtistClient $client): Response => $client->artistAlbums($artistId, $limit, $offset),
                        userRequest: fn (SpotifyArtistClient $client): Response => $client->artistAlbums($artistId, $limit, $offset),
                        preferUser: true,
                    );

                    if (in_array($response->status(), self::EMPTY_PAYLOAD_STATUSES, true)) {
                        return [];
                    }

                    $items = $response->throw()->json('items', []);

                    if ($items === []) {
                        break;
                    }

                    $albums = [...$albums, ...$items];

                    if (count($items) < $limit) {
                        break;
                    }
                }

                return $albums;
            } catch (\Throwable $e) {
                Log::channel('spotify')->warning('Spotify artistAlbums failed', ['artist_id' => $artistId, 'error' => $e->getMessage()]);
                $this->activateArtistCooldown($artistId);

                return [];
            }
        });
    }

    public function album(User $user, string $albumId): ?array
    {
        $payload = $this->cached($user, 'album_profile', 'v2:'.$albumId, function () use ($user, $albumId) {
            return $this->fetchNullablePayload(
                user: $user,
                operation: 'album',
                appRequest: fn (SpotifyArtistClient $client): Response => $client->album($albumId),
                userRequest: fn (SpotifyArtistClient $client): Response => $client->album($albumId),
                preferUser: true,
            );
        });

        return $payload === [] ? null : $payload;
    }

    public function albumTracks(User $user, string $albumId): array
    {
        return $this->cached($user, 'album_tracks', 'v2:'.$albumId, function () use ($user, $albumId) {
            return $this->fetchArrayPayload(
                user: $user,
                operation: 'albumTracks',
                appRequest: fn (SpotifyArtistClient $client): Response => $client->albumTracks($albumId),
                userRequest: fn (SpotifyArtistClient $client): Response => $client->albumTracks($albumId),
                preferUser: true,
            );
        });
    }

    public function isArtistFollowed(User $user, string $artistId): bool
    {
        return $this->libraryContains($user, $this->artistUri($artistId), 'isArtistFollowed', ['artist_id' => $artistId]);
    }

    public function followArtist(User $user, string $artistId): bool
    {
        return $this->saveLibraryItem($user, $this->artistUri($artistId), 'followArtist', ['artist_id' => $artistId]);
    }

    public function unfollowArtist(User $user, string $artistId): bool
    {
        return $this->removeLibraryItem($user, $this->artistUri($artistId), 'unfollowArtist', ['artist_id' => $artistId]);
    }

    public function isAlbumSaved(User $user, string $albumId): bool
    {
        return $this->libraryContains($user, $this->albumUri($albumId), 'isAlbumSaved', ['album_id' => $albumId]);
    }

    public function saveAlbum(User $user, string $albumId): bool
    {
        return $this->saveLibraryItem($user, $this->albumUri($albumId), 'saveAlbum', ['album_id' => $albumId]);
    }

    public function unsaveAlbum(User $user, string $albumId): bool
    {
        return $this->removeLibraryItem($user, $this->albumUri($albumId), 'unsaveAlbum', ['album_id' => $albumId]);
    }

    private function artistUri(string $artistId): string
    {
        return 'spotify:artist:'.$artistId;
    }

    private function albumUri(string $albumId): string
    {
        return 'spotify:album:'.$albumId;
    }

    private function libraryContains(User $user, string $uri, string $operation, array $context = []): bool
    {
        try {
            $response = $this->userArtistClient($user)->libraryContains([$uri]);

            if (in_array($response->status(), self::EMPTY_PAYLOAD_STATUSES, true)) {
                return false;
            }

            return (bool) data_get($response->throw()->json(), '0', false);
        } catch (\Throwable $e) {
            Log::channel('spotify')->warning("Spotify {$operation} failed", ['error' => $e->getMessage(), ...$context]);

            return false;
        }
    }

    private function saveLibraryItem(User $user, string $uri, string $operation, array $context = []): bool
    {
        return $this->saveMutation(
            operation: $operation,
            request: fn (): Response => $this->userArtistClient($user)->saveToLibrary([$uri]),
            context: $context,
        );
    }

    private function removeLibraryItem(User $user, string $uri, string $operation, array $context = []): bool
    {
        return $this->saveMutation(
            operation: $operation,
            request: fn (): Response => $this->userArtistClient($user)->removeFromLibrary([$uri]),
            context: $context,
        );
    }

    private function fetchArrayPayload(
        User $user,
        string $operation,
        Closure $appRequest,
        Closure $userRequest,
        string $path = 'items',
        bool $preferUser = false,
    ): array {
        try {
            $response = $this->requestWithFallback($user, $appRequest, $userRequest, $preferUser);

            if (in_array($response->status(), self::EMPTY_PAYLOAD_STATUSES, true)) {
                return [];
            }

            return $response->throw()->json($path, []);
        } catch (\Throwable $e) {
            Log::channel('spotify')->warning("Spotify {$operation} failed", ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function fetchNullablePayload(
        User $user,
        string $operation,
        Closure $appRequest,
        Closure $userRequest,
        bool $preferUser = false,
    ): array {
        try {
            $response = $this->requestWithFallback($user, $appRequest, $userRequest, $preferUser);

            if (in_array($response->status(), self::EMPTY_PAYLOAD_STATUSES, true)) {
                return [];
            }

            return $response->throw()->json();
        } catch (\Throwable $e) {
            Log::channel('spotify')->warning("Spotify {$operation} failed", ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function requestWithFallback(User $user, Closure $appRequest, ?Closure $userRequest = null, bool $preferUser = false): Response
    {
        if ($preferUser && $userRequest !== null) {
            $userResponse = $userRequest($this->userArtistClient($user));
            $userStatus = $userResponse->status();

            if (! in_array($userStatus, [401, ...self::FALLBACK_STATUSES], true)) {
                return $userResponse;
            }

            $appResponse = $appRequest($this->appArtistClient());

            if ($userStatus === 429 && in_array($appResponse->status(), self::EMPTY_PAYLOAD_STATUSES, true)) {
                return $userResponse;
            }

            return $appResponse;
        }

        $response = $appRequest($this->appArtistClient());

        if ($response->status() === 429) {
            return $response;
        }

        if (in_array($response->status(), self::FALLBACK_STATUSES, true) && $userRequest !== null) {
            $userResponse = $userRequest($this->userArtistClient($user));

            if (in_array($userResponse->status(), self::EMPTY_PAYLOAD_STATUSES, true)) {
                return $response;
            }

            return $userResponse;
        }

        return $response;
    }

    private function isArtistInCooldown(string $artistId): bool
    {
        return Cache::has($this->artistCooldownKey($artistId));
    }

    private function activateArtistCooldown(string $artistId): void
    {
        Cache::put(
            $this->artistCooldownKey($artistId),
            true,
            now()->addSeconds(self::ARTIST_FETCH_COOLDOWN_SECONDS),
        );
    }

    private function artistCooldownKey(string $artistId): string
    {
        return 'spotify:artist:cooldown:'.$artistId;
    }

    /**
     * @return SpotifyPayloadList
     */
    private function albumsFromDatabase(string $artistId): array
    {
        $artist = Artist::query()
            ->where('artist_id', $artistId)
            ->with(['albums', 'tracks.album'])
            ->first();

        if (! $artist) {
            return [];
        }

        $albums = $artist->albums;

        if ($albums->isEmpty()) {
            $albums = $artist->tracks
                ->map(fn (Track $track): ?Album => $track->album)
                ->filter(fn (?Album $album): bool => $album !== null)
                ->unique('id')
                ->values();
        }

        if ($albums->isEmpty()) {
            return [];
        }

        return $albums
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
            ->all();
    }

    /**
     * @return SpotifyPayloadList
     */
    private function topTracksFromDatabase(string $artistId): array
    {
        $artist = Artist::query()
            ->where('artist_id', $artistId)
            ->with(['tracks.album', 'tracks.artists'])
            ->first();

        if (! $artist || $artist->tracks->isEmpty()) {
            return [];
        }

        return $artist->tracks
            ->take(10)
            ->map(fn (Track $track): array => [
                'id' => $track->spotify_id,
                'uri' => 'spotify:track:'.$track->spotify_id,
                'name' => $track->name,
                'artists' => $track->artists
                    ->map(fn (Artist $item): array => [
                        'id' => $item->artist_id,
                        'name' => $item->artist_name ?? 'Unknown Artist',
                        'external_urls' => [
                            'spotify' => 'https://open.spotify.com/artist/'.$item->artist_id,
                        ],
                    ])
                    ->values()
                    ->all(),
                'album' => [
                    'id' => $track->album?->spotify_id,
                    'name' => $track->album?->name,
                    /** @phpstan-ignore nullsafe.neverNull */
                    'images' => $track->album?->images ?? [],
                    /** @phpstan-ignore nullsafe.neverNull */
                    'release_date' => $track->album?->release_date ?? '',
                    'album_type' => $track->album?->album_type,
                    'total_tracks' => $track->album?->total_tracks,
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
            ->all();
    }

    private function saveMutation(string $operation, Closure $request, array $context = []): bool
    {
        try {
            $response = $request();

            if (in_array($response->status(), [200, 202, 204], true)) {
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            Log::channel('spotify')->warning("Spotify {$operation} failed", ['error' => $e->getMessage(), ...$context]);

            return false;
        }
    }

    private function appArtistClient(): SpotifyArtistClient
    {
        return new SpotifyArtistClient($this->tokenService->appAccessToken());
    }

    private function userArtistClient(User $user): SpotifyArtistClient
    {
        return new SpotifyArtistClient($this->tokenService->ensureFreshToken($user));
    }
}
