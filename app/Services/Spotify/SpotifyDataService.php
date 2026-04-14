<?php

namespace App\Services\Spotify;

use App\Models\SpotifyStat;
use App\Models\User;
use Closure;
use Illuminate\Http\Client\Response;

readonly class SpotifyDataService
{
    public function __construct(
        private SpotifyTokenService $tokenService,
    ) {}

    public function topTracks(User $user, string $timeRange = 'medium_term'): array
    {
        return $this->cached($user, 'top_tracks', $timeRange, function () use ($user, $timeRange) {
            try {
                $token = $this->tokenService->ensureFreshToken($user);

                $response = (new SpotifyClient($token))->topTracks($timeRange);

                if (in_array($response->status(), [401, 403])) {
                    return [];
                }

                return $response->throw()->json('items', []);
            } catch (\Throwable) {
                return [];
            }
        });
    }

    public function topArtists(User $user, string $timeRange = 'medium_term'): array
    {
        return $this->cached($user, 'top_artists', $timeRange, function () use ($user, $timeRange) {
            try {
                $token = $this->tokenService->ensureFreshToken($user);

                $response = (new SpotifyClient($token))->topArtists($timeRange);

                if (in_array($response->status(), [401, 403])) {
                    return [];
                }

                return $response->throw()->json('items', []);
            } catch (\Throwable) {
                return [];
            }
        });
    }

    public function recentlyPlayed(User $user): array
    {
        return $this->cached($user, 'recently_played', 'none', function () use ($user) {
            try {
                $token = $this->tokenService->ensureFreshToken($user);

                $response = (new SpotifyClient($token))->recentlyPlayed();

                if (in_array($response->status(), [401, 403])) {
                    return [];
                }

                return $response->throw()->json('items', []);
            } catch (\Throwable) {
                return [];
            }
        });
    }

    public function recentlyPlayedUnique(User $user): array
    {
        $items = $this->recentlyPlayed($user);

        $seen = [];

        return array_values(
            array_filter($items, function (array $item) use (&$seen) {
                $trackId = $item['track']['id'] ?? null;

                if ($trackId === null || isset($seen[$trackId])) {
                    return false;
                }

                $seen[$trackId] = true;

                return true;
            }),
        );
    }

    public function currentlyPlaying(User $user): ?array
    {
        try {
            $token = $this->tokenService->ensureFreshToken($user);

            $client = new SpotifyClient($token);
            $response = $client->playbackState();

            if (in_array($response->status(), [204, 401, 403]) || $response->body() === '') {
                return null;
            }

            $response->throw();

            $data = $response->json();

            if (empty($data['item'])) {
                return null;
            }

            if (($data['currently_playing_type'] ?? 'track') !== 'track') {
                return null;
            }

            return [
                'is_playing' => $data['is_playing'] ?? false,
                'shuffle_state' => $data['shuffle_state'] ?? false,
                'progress_ms' => $data['progress_ms'] ?? 0,
                'track' => $data['item'],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Send a player command. Returns true on success, false on 403 (Premium required) or error.
     *
     * @param  callable(SpotifyClient): Response  $command
     */
    public function command(User $user, callable $command): bool
    {
        try {
            $token = $this->tokenService->ensureFreshToken($user);
            $response = $command(new SpotifyClient($token));

            return in_array($response->status(), [200, 202, 204]);
        } catch (\Throwable) {
            return false;
        }
    }

    public function play(User $user, string $uri): bool
    {
        try {
            $token = $this->tokenService->ensureFreshToken($user);
            $client = new SpotifyClient($token);

            $response = $client->play([$uri]);

            if (in_array($response->status(), [200, 202, 204])) {
                return true;
            }

            if ($response->status() === 404) {
                $devicesResponse = $client->devices();
                $devices = $devicesResponse->json('devices', []);
                $deviceId = collect($devices)
                    ->whereNotNull('id')
                    ->where('is_restricted', false)
                    ->value('id');

                if ($deviceId) {
                    $retry = $client->play([$uri], $deviceId);

                    return in_array($retry->status(), [200, 202, 204]);
                }
            }

            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    public function transferPlayback(User $user, string $deviceId, bool $play = true): bool
    {
        return $this->command(
            $user,
            fn (SpotifyClient $client) => $client->transferPlayback($deviceId, $play),
        );
    }

    public function devices(User $user): array
    {
        try {
            $token = $this->tokenService->ensureFreshToken($user);
            $response = (new SpotifyClient($token))->devices();

            if (in_array($response->status(), [401, 403])) {
                return [];
            }

            return $response->throw()->json('devices', []);
        } catch (\Throwable) {
            return [];
        }
    }

    private function cached(User $user, string $type, string $timeRange, Closure $fetch): array
    {
        $stat = SpotifyStat::firstOrNew([
            'user_id' => $user->id,
            'type' => $type,
            'time_range' => $timeRange,
        ]);

        if (! $stat->exists || $stat->isExpired()) {
            $stat->payload = $fetch();
            $stat->fetched_at = now();
            $stat->expires_at = now()->addSeconds($this->ttl($type, $timeRange));
            $stat->save();
        }

        return $stat->payload;
    }

    private function ttl(string $type, string $timeRange): int
    {
        if ($type === 'recently_played') {
            return 15 * 60;
        }

        return match ($timeRange) {
            'long_term' => 72 * 3600,
            default => 24 * 3600,
        };
    }
}
