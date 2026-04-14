<?php

namespace App\Services\Spotify;

use App\Models\SpotifyStat;
use App\Models\User;
use Closure;
use Illuminate\Http\Client\Response;

class SpotifyDataService
{
    public function __construct(
        private readonly SpotifyTokenService $tokenService,
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

    /**
     * Fetch the currently playing track — never cached, always live.
     *
     * Returns null when nothing is playing (HTTP 204), on permission errors,
     * or when the currently playing type is not a track.
     */
    public function currentlyPlaying(User $user): ?array
    {
        try {
            $token = $this->tokenService->ensureFreshToken($user);

            $response = (new SpotifyClient($token))->currentlyPlaying();

            // 204 = nothing playing, 401/403 = missing scope
            if (in_array($response->status(), [204, 401, 403]) || $response->body() === '') {
                return null;
            }

            $response->throw();

            $data = $response->json();

            if (($data['currently_playing_type'] ?? '') !== 'track' || empty($data['item'])) {
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

            return in_array($response->status(), [200, 204]);
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
