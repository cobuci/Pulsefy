<?php

namespace App\Services\Spotify\Artist;

use App\Models\User;
use App\Services\Spotify\Client\SpotifyArtistClient;
use App\Services\Spotify\Concerns\CachesStats;
use App\Services\Spotify\Contracts\SpotifyArtistProvider;
use App\Services\Spotify\SpotifyTokenService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

final readonly class SpotifyArtistService implements SpotifyArtistProvider
{
    use CachesStats;

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
                    appRequest: fn (SpotifyArtistClient $client): Response => $client->searchTracksByArtist($artist['name']),
                    userRequest: fn (SpotifyArtistClient $client): Response => $client->searchTracksByArtist($artist['name']),
                );

                if ($response === null || in_array($response->status(), [401, 403, 404])) {
                    return [];
                }

                $items = $response->throw()->json('tracks.items', []);

                return array_values(array_filter($items, fn (array $track) => collect($track['artists'] ?? [])->contains(fn (array $a) => ($a['id'] ?? null) === $artistId)));
            } catch (\Throwable $e) {
                Log::warning('Spotify topTracks failed', ['artist_id' => $artistId, 'error' => $e->getMessage()]);

                return [];
            }
        });
    }

    public function albums(User $user, string $artistId): array
    {
        return $this->cached($user, 'artist_albums', 'v1:'.$artistId, function () use ($user, $artistId) {
            return $this->fetchArrayPayload(
                user: $user,
                operation: 'artistAlbums',
                appRequest: fn (SpotifyArtistClient $client): Response => $client->artistAlbums($artistId),
                userRequest: fn (SpotifyArtistClient $client): Response => $client->artistAlbums($artistId),
            );
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

    private function fetchArrayPayload(User $user, string $operation, \Closure $appRequest, \Closure $userRequest, string $path = 'items'): array
    {
        try {
            $response = $this->requestWithFallback($user, $appRequest, $userRequest);

            if ($response === null || in_array($response->status(), [401, 403, 404])) {
                return [];
            }

            return $response->throw()->json($path, []);
        } catch (\Throwable $e) {
            Log::warning("Spotify {$operation} failed", ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function fetchNullablePayload(User $user, string $operation, \Closure $appRequest, \Closure $userRequest): array
    {
        try {
            $response = $this->requestWithFallback($user, $appRequest, $userRequest);

            if ($response === null || in_array($response->status(), [401, 403, 404])) {
                return [];
            }

            return $response->throw()->json();
        } catch (\Throwable $e) {
            Log::warning("Spotify {$operation} failed", ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function requestWithFallback(User $user, \Closure $appRequest, ?\Closure $userRequest = null): ?Response
    {
        $response = $appRequest($this->appArtistClient());

        if ($response->status() === 403 && $userRequest !== null) {
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
