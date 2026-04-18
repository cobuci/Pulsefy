<?php

namespace App\Services\Spotify;

use App\Models\User;
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
            Log::warning("Spotify {$operation} failed", ['error' => $e->getMessage()]);

            return [];
        }
    }
}
