<?php

namespace App\Services\Spotify\Playback;

use App\Models\Playlist;
use App\Models\PlaylistTrack;
use App\Models\User;
use App\Services\Spotify\Client\SpotifyPlaybackClient;
use App\Services\Spotify\Contracts\SpotifyPlaybackProvider;
use App\Services\Spotify\SpotifyTokenService;
use Illuminate\Support\Facades\Log;

/**
 * @phpstan-type PlaybackPayload array{is_playing: bool, shuffle_state: bool, repeat_state: string, progress_ms: int, volume_percent: ?int, device_id: ?string, device_name: ?string, track: array<string, mixed>, is_saved: bool}
 */
final readonly class SpotifyPlaybackService implements SpotifyPlaybackProvider
{
    public function __construct(
        private SpotifyTokenService $tokenService,
    ) {}

    public function currentlyPlaying(User $user): ?array
    {
        try {
            $response = $this->client($user)->playbackState();

            if (in_array($response->status(), [204, 401, 403], true) || $response->body() === '') {
                return null;
            }

            $response->throw();

            $data = $response->json();

            if (empty($data['item']) || ($data['currently_playing_type'] ?? 'track') !== 'track') {
                return null;
            }

            $trackId = $data['item']['id'] ?? '';

            return [
                'is_playing' => $data['is_playing'] ?? false,
                'shuffle_state' => $data['shuffle_state'] ?? false,
                'repeat_state' => $data['repeat_state'] ?? 'off',
                'progress_ms' => $data['progress_ms'] ?? 0,
                'volume_percent' => $data['device']['volume_percent'] ?? null,
                'device_id' => $data['device']['id'] ?? null,
                'device_name' => $data['device']['name'] ?? null,
                'track' => $data['item'],
                'is_saved' => $trackId !== '' ? $this->isTrackSaved($user, $trackId) : false,
            ];
        } catch (\Throwable $e) {
            Log::channel('spotify')->warning('Spotify currentlyPlaying failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function devices(User $user): array
    {
        try {
            $response = $this->client($user)->devices();

            if (in_array($response->status(), [401, 403], true)) {
                return [];
            }

            return $response->throw()->json('devices', []);
        } catch (\Throwable $e) {
            Log::channel('spotify')->warning('Spotify devices failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function resumePlay(User $user): bool
    {
        return $this->statusOk(fn () => $this->client($user)->play(), 'resumePlay');
    }

    public function play(User $user, string $uri): bool
    {
        try {
            $client = $this->client($user);
            $response = $client->play([$uri]);

            if (in_array($response->status(), [200, 202, 204], true)) {
                return true;
            }

            if ($response->status() === 404) {
                $deviceId = collect($client->devices()->json('devices', []))
                    ->whereNotNull('id')
                    ->where('is_restricted', false)
                    ->value('id');

                if ($deviceId) {
                    $retry = $client->play([$uri], $deviceId);

                    return in_array($retry->status(), [200, 202, 204], true);
                }
            }

            return false;
        } catch (\Throwable $e) {
            Log::channel('spotify')->warning('Spotify play failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function playMany(User $user, array $uris, ?int $offsetPosition = null): bool
    {
        try {
            $client = $this->client($user);
            $response = $client->playContext($uris, $offsetPosition);

            if (in_array($response->status(), [200, 202, 204], true)) {
                return true;
            }

            if ($response->status() === 404) {
                $deviceId = collect($client->devices()->json('devices', []))
                    ->whereNotNull('id')
                    ->where('is_restricted', false)
                    ->value('id');

                if ($deviceId) {
                    $retry = $client->playContext($uris, $offsetPosition, $deviceId);

                    return in_array($retry->status(), [200, 202, 204], true);
                }
            }

            return false;
        } catch (\Throwable $e) {
            Log::channel('spotify')->warning('Spotify playMany failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function pause(User $user): bool
    {
        return $this->statusOk(fn () => $this->client($user)->pause(), 'pause');
    }

    public function next(User $user): bool
    {
        return $this->statusOk(fn () => $this->client($user)->next(), 'next');
    }

    public function previous(User $user): bool
    {
        return $this->statusOk(fn () => $this->client($user)->previous(), 'previous');
    }

    public function seek(User $user, int $positionMs): bool
    {
        return $this->statusOk(fn () => $this->client($user)->seek($positionMs), 'seek');
    }

    public function setVolume(User $user, int $volumePercent): bool
    {
        return $this->statusOk(fn () => $this->client($user)->setVolume($volumePercent), 'setVolume');
    }

    public function setShuffle(User $user, bool $state): bool
    {
        return $this->statusOk(fn () => $this->client($user)->shuffle($state), 'setShuffle');
    }

    public function setRepeat(User $user, string $mode): bool
    {
        if (! in_array($mode, ['off', 'context', 'track'], true)) {
            return false;
        }

        return $this->statusOk(fn () => $this->client($user)->repeat($mode), 'setRepeat');
    }

    public function transferPlayback(User $user, string $deviceId, bool $play = true): bool
    {
        return $this->statusOk(fn () => $this->client($user)->transferPlayback($deviceId, $play), 'transferPlayback');
    }

    public function isTrackSaved(User $user, string $trackId): bool
    {
        $likedPlaylist = $this->likedPlaylist($user);

        if ($likedPlaylist === null) {
            return $this->isTrackSavedFromApi($user, $trackId);
        }

        $inDb = PlaylistTrack::query()
            ->where('playlist_id', $likedPlaylist->id)
            ->where('spotify_track_id', $trackId)
            ->exists();

        if ($inDb) {
            return true;
        }

        $savedOnApi = $this->isTrackSavedFromApi($user, $trackId);

        if ($savedOnApi) {
            PlaylistTrack::query()->updateOrCreate(
                [
                    'playlist_id' => $likedPlaylist->id,
                    'spotify_track_id' => $trackId,
                ],
                [
                    'position' => PlaylistTrack::query()->where('playlist_id', $likedPlaylist->id)->max('position') + 1,
                    'added_at' => now(),
                ],
            );
        }

        return $savedOnApi;
    }

    public function saveTrack(User $user, string $trackId): bool
    {
        $result = $this->statusOk(fn () => $this->client($user)->saveTrack($trackId), 'saveTrack');

        if ($result) {
            $likedPlaylist = $this->likedPlaylist($user);

            if ($likedPlaylist !== null) {
                PlaylistTrack::query()->updateOrCreate(
                    [
                        'playlist_id' => $likedPlaylist->id,
                        'spotify_track_id' => $trackId,
                    ],
                    [
                        'position' => PlaylistTrack::query()->where('playlist_id', $likedPlaylist->id)->max('position') + 1,
                        'added_at' => now(),
                    ],
                );
            }
        }

        return $result;
    }

    public function unsaveTrack(User $user, string $trackId): bool
    {
        $result = $this->statusOk(fn () => $this->client($user)->unsaveTrack($trackId), 'unsaveTrack');

        if ($result) {
            $likedPlaylist = $this->likedPlaylist($user);

            if ($likedPlaylist !== null) {
                PlaylistTrack::query()
                    ->where('playlist_id', $likedPlaylist->id)
                    ->where('spotify_track_id', $trackId)
                    ->delete();
            }
        }

        return $result;
    }

    private function likedPlaylist(User $user): ?Playlist
    {
        return Playlist::query()
            ->where('user_id', $user->id)
            ->where('is_liked_playlist', true)
            ->first();
    }

    private function isTrackSavedFromApi(User $user, string $trackId): bool
    {
        try {
            $response = $this->client($user)->isTrackSaved($trackId);

            if (in_array($response->status(), [401, 403, 404], true)) {
                return false;
            }

            return (bool) data_get($response->throw()->json(), '0', false);
        } catch (\Throwable $e) {
            Log::channel('spotify')->warning('Spotify isTrackSaved failed', ['error' => $e->getMessage(), 'track_id' => $trackId]);

            return false;
        }
    }

    private function statusOk(\Closure $callback, string $operation): bool
    {
        try {
            $response = $callback();

            return in_array($response->status(), [200, 202, 204], true);
        } catch (\Throwable $e) {
            Log::channel('spotify')->warning("Spotify {$operation} failed", ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function client(User $user): SpotifyPlaybackClient
    {
        return new SpotifyPlaybackClient($this->tokenService->ensureFreshToken($user));
    }
}
