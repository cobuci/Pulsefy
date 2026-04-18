<?php

namespace App\Services\Spotify\Artist;

use App\Models\SpotifyStat;
use App\Models\User;
use App\Services\Spotify\Client\SpotifyArtistClient;
use App\Services\Spotify\Concerns\CachesStats;
use App\Services\Spotify\Contracts\SpotifyArtistProvider;
use App\Services\Spotify\SpotifyTokenService;
use Closure;
use Illuminate\Http\Client\Response;
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

    public function __construct(
        private SpotifyTokenService $tokenService,
    ) {}

    public function artist(User $user, string $artistId): ?array
    {
        $payload = $this->cached($user, 'artist_profile', 'v2:'.$artistId, function () use ($user, $artistId) {
            return $this->fetchNullablePayload(
                user: $user,
                operation: 'artist',
                appRequest: fn (SpotifyArtistClient $client): Response => $client->artist($artistId),
                userRequest: fn (SpotifyArtistClient $client): Response => $client->artist($artistId),
            );
        });

        if ($payload === []) {
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

    public function topTracks(User $user, string $artistId): array
    {
        return $this->cached($user, 'artist_top_tracks', 'v2:'.$artistId, function () use ($user, $artistId) {
            $tracks = $this->fetchTopTracks($user, $artistId);

            if ($tracks === []) {
                return $this->searchTopTracksFallback($user, $artistId);
            }

            return $tracks;
        });
    }

    /**
     * @return SpotifyPayloadList
     */
    private function fetchTopTracks(User $user, string $artistId): array
    {
        try {
            $response = $this->requestWithFallback(
                user: $user,
                appRequest: fn (SpotifyArtistClient $client): Response => $client->artistTopTracks($artistId),
                userRequest: fn (SpotifyArtistClient $client): Response => $client->artistTopTracks($artistId),
            );

            if (in_array($response->status(), self::EMPTY_PAYLOAD_STATUSES, true)) {
                return [];
            }

            /** @var SpotifyPayloadList $tracks */
            $tracks = $response->throw()->json('tracks', []);

            return $tracks;
        } catch (\Throwable $e) {
            Log::channel('spotify')->warning('Spotify topTracks failed', ['artist_id' => $artistId, 'error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return SpotifyPayloadList
     */
    private function searchTopTracksFallback(User $user, string $artistId): array
    {
        try {
            $artistResponse = $this->requestWithFallback(
                user: $user,
                appRequest: fn (SpotifyArtistClient $client): Response => $client->artist($artistId),
                userRequest: fn (SpotifyArtistClient $client): Response => $client->artist($artistId),
            );

            if (in_array($artistResponse->status(), self::EMPTY_PAYLOAD_STATUSES, true)) {
                return [];
            }

            $artistName = (string) $artistResponse->throw()->json('name', '');

            if ($artistName === '') {
                return [];
            }

            $tracksResponse = $this->requestWithFallback(
                user: $user,
                appRequest: fn (SpotifyArtistClient $client): Response => $client->searchTracksByArtist($artistName),
                userRequest: fn (SpotifyArtistClient $client): Response => $client->searchTracksByArtist($artistName),
            );

            if (in_array($tracksResponse->status(), self::EMPTY_PAYLOAD_STATUSES, true)) {
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

            return [];
        }
    }

    public function albums(User $user, string $artistId): array
    {
        return $this->cached($user, 'artist_albums', 'v1:'.$artistId, function () use ($user, $artistId) {
            try {
                $albums = [];
                $limit = 10;

                for ($offset = 0; $offset < 50; $offset += $limit) {
                    $response = $this->requestWithFallback(
                        user: $user,
                        appRequest: fn (SpotifyArtistClient $client): Response => $client->artistAlbums($artistId, $limit, $offset),
                        userRequest: fn (SpotifyArtistClient $client): Response => $client->artistAlbums($artistId, $limit, $offset),
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

    private function fetchArrayPayload(User $user, string $operation, Closure $appRequest, Closure $userRequest, string $path = 'items'): array
    {
        try {
            $response = $this->requestWithFallback($user, $appRequest, $userRequest);

            if (in_array($response->status(), self::EMPTY_PAYLOAD_STATUSES, true)) {
                return [];
            }

            return $response->throw()->json($path, []);
        } catch (\Throwable $e) {
            Log::channel('spotify')->warning("Spotify {$operation} failed", ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function fetchNullablePayload(User $user, string $operation, Closure $appRequest, Closure $userRequest): array
    {
        try {
            $response = $this->requestWithFallback($user, $appRequest, $userRequest);

            if (in_array($response->status(), self::EMPTY_PAYLOAD_STATUSES, true)) {
                return [];
            }

            return $response->throw()->json();
        } catch (\Throwable $e) {
            Log::channel('spotify')->warning("Spotify {$operation} failed", ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function requestWithFallback(User $user, Closure $appRequest, ?Closure $userRequest = null): Response
    {
        $response = $appRequest($this->appArtistClient());

        if (in_array($response->status(), self::FALLBACK_STATUSES, true) && $userRequest !== null) {
            return $userRequest($this->userArtistClient($user));
        }

        return $response;
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
