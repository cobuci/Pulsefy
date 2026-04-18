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
 * @phpstan-type ArtistTrack array{artists?: array<int, array{id?: ?string}>}
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
        return $this->cached($user, 'artist_top_tracks', 'v1:'.$artistId, function () use ($user, $artistId) {
            try {
                $artist = $this->artist($user, $artistId);

                if (! $artist || empty($artist['name'])) {
                    return [];
                }

                $response = $this->requestWithFallback(
                    user: $user,
                    appRequest: fn (SpotifyArtistClient $client): Response => $client->searchTracksByArtist($artist['name'], 50),
                    userRequest: fn (SpotifyArtistClient $client): Response => $client->searchTracksByArtist($artist['name'], 50),
                );

                if (in_array($response->status(), self::EMPTY_PAYLOAD_STATUSES, true)) {
                    return [];
                }

                $items = $response->throw()->json('tracks.items', []);

                return array_values(array_filter($items, fn (array $track): bool => $this->trackContainsArtist($track, $artistId)));
            } catch (\Throwable $e) {
                Log::warning('Spotify topTracks failed', ['artist_id' => $artistId, 'error' => $e->getMessage()]);

                return [];
            }
        });
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
                Log::warning('Spotify artistAlbums failed', ['artist_id' => $artistId, 'error' => $e->getMessage()]);

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
            Log::warning("Spotify {$operation} failed", ['error' => $e->getMessage()]);

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
            Log::warning("Spotify {$operation} failed", ['error' => $e->getMessage()]);

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

    private function trackContainsArtist(array $track, string $artistId): bool
    {
        foreach ($track['artists'] ?? [] as $artist) {
            if (($artist['id'] ?? null) === $artistId) {
                return true;
            }
        }

        return false;
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
