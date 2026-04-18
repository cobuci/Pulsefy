<?php

namespace App\Services\Spotify;

use App\Models\User;
use App\Services\Spotify\Artist\ArtistGenreCacheService;
use App\Services\Spotify\Client\SpotifyStatsClient;
use App\Services\Spotify\Concerns\CachesStats;
use App\Services\Spotify\Contracts\SpotifyStatsProvider;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

/**
 * @phpstan-type SpotifyPayloadList array<int, array<string, mixed>>
 */
final readonly class SpotifyService implements SpotifyStatsProvider
{
    use CachesStats;

    public function __construct(
        private SpotifyTokenService $tokenService,
        private ArtistGenreCacheService $artistGenreCache,
    ) {}

    public function topTracks(User $user, string $timeRange = 'medium_term'): array
    {
        return $this->cached($user, 'top_tracks', $timeRange, function () use ($user, $timeRange) {
            return $this->fetchStatsItems(
                operation: 'topTracks',
                request: fn (SpotifyStatsClient $client): Response => $client->topTracks($timeRange),
                user: $user,
            );
        });
    }

    public function topArtists(User $user, string $timeRange = 'medium_term'): array
    {
        return $this->cached($user, 'top_artists', $timeRange, function () use ($user, $timeRange) {
            return $this->fetchStatsItems(
                operation: 'topArtists',
                request: fn (SpotifyStatsClient $client): Response => $client->topArtists($timeRange),
                user: $user,
            );
        });
    }

    public function recentlyPlayed(User $user): array
    {
        return $this->cached($user, 'recently_played', 'none', function () use ($user) {
            return $this->fetchStatsItems(
                operation: 'recentlyPlayed',
                request: fn (SpotifyStatsClient $client): Response => $client->recentlyPlayed(),
                user: $user,
            );
        });
    }

    public function recentlyPlayedUnique(User $user): array
    {
        $seen = [];

        return array_values(
            array_filter($this->recentlyPlayed($user), function (array $item) use (&$seen) {
                $trackId = $item['track']['id'] ?? null;

                if ($trackId === null || isset($seen[$trackId])) {
                    return false;
                }

                $seen[$trackId] = true;

                return true;
            }),
        );
    }

    public function topItemsSnapshot(User $user): array
    {
        return $this->cached($user, 'top_items_snapshot', 'v2', function () use ($user): array {
            $snapshot = [];

            foreach (['short_term', 'medium_term', 'long_term'] as $timeRange) {
                $snapshot[$timeRange] = [
                    'tracks' => $this->fetchTopTracksPaginated($user, $timeRange),
                    'artists' => $this->fetchTopArtistsPaginated($user, $timeRange),
                ];
            }

            return $snapshot;
        });
    }

    private function statsClient(User $user): SpotifyStatsClient
    {
        return new SpotifyStatsClient($this->tokenService->ensureFreshToken($user));
    }

    private function fetchStatsItems(string $operation, \Closure $request, User $user, string $path = 'items'): array
    {
        try {
            $response = $request($this->statsClient($user));

            if (in_array($response->status(), [401, 403], true)) {
                return [];
            }

            return $response->throw()->json($path, []);
        } catch (\Throwable $e) {
            Log::channel('spotify')->warning("Spotify {$operation} failed", ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchTopTracksPaginated(User $user, string $timeRange): array
    {
        return $this->fetchTopItemsPaginated(
            operation: 'topTracksSnapshot',
            request: fn (SpotifyStatsClient $client, int $limit, int $offset): Response => $client->topTracksPage($timeRange, $limit, $offset),
            user: $user,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchTopArtistsPaginated(User $user, string $timeRange): array
    {
        $artists = $this->fetchTopItemsPaginated(
            operation: 'topArtistsSnapshot',
            request: fn (SpotifyStatsClient $client, int $limit, int $offset): Response => $client->topArtistsPage($timeRange, $limit, $offset),
            user: $user,
        );

        return $this->artistGenreCache->mergeGenres($artists);
    }

    /**
     * @param  \Closure(SpotifyStatsClient, int, int): Response  $request
     * @return array<int, array<string, mixed>>
     */
    private function fetchTopItemsPaginated(string $operation, \Closure $request, User $user): array
    {
        $limit = 50;
        $offset = 0;
        $pages = 2;
        $items = [];

        try {
            $client = $this->statsClient($user);

            for ($page = 0; $page < $pages; $page++) {
                $response = $request($client, $limit, $offset);

                if (in_array($response->status(), [401, 403], true)) {
                    return [];
                }

                $response->throw();

                $chunk = $response->json('items', []);

                if (! is_array($chunk) || $chunk === []) {
                    break;
                }

                $items = [...$items, ...$chunk];

                if (count($chunk) < $limit) {
                    break;
                }

                $offset += $limit;
            }

            return $items;
        } catch (\Throwable $e) {
            Log::channel('spotify')->warning("Spotify {$operation} failed", ['error' => $e->getMessage()]);

            return [];
        }
    }
}
