<?php

namespace App\Services\Spotify\Artist;

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

    private const int FALLBACK_STATUS = 403;

    private const array EMPTY_PAYLOAD_STATUSES = [401, 403, 404];

    public function __construct(
        private SpotifyTokenService $tokenService,
    ) {}

    public function artist(User $user, string $artistId): ?array
    {
        $payload = $this->cached($user, 'artist_profile', 'v1:'.$artistId, function () use ($user, $artistId) {
            return $this->fetchNullablePayload(
                user: $user,
                operation: 'artist',
                appRequest: fn (SpotifyArtistClient $client): Response => $client->artist($artistId),
                userRequest: fn (SpotifyArtistClient $client): Response => $client->artist($artistId),
            );
        });

        return $payload === [] ? null : $payload;
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

        if ($response->status() === self::FALLBACK_STATUS && $userRequest !== null) {
            return $userRequest($this->userArtistClient($user));
        }

        return $response;
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
