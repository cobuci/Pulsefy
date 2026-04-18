<?php

namespace App\Services\Spotify\Sync;

use App\Models\Album;
use App\Models\Artist;
use App\Models\SpotifySyncRun;
use App\Models\Track;
use App\Models\User;
use App\Models\UserRecentPlay;
use App\Models\UserTopArtist;
use App\Models\UserTopTrack;
use App\Services\Spotify\Client\SpotifyStatsClient;
use App\Services\Spotify\SpotifyTokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class SpotifySyncService
{
    public function __construct(private SpotifyTokenService $tokenService) {}

    public function syncTopArtists(User $user): void
    {
        $run = $this->startRun($user, 'top_artists');

        try {
            $client = $this->client($user);

            foreach (['short_term', 'medium_term', 'long_term'] as $timeRange) {
                $items = $client->topArtistsPage($timeRange, 50, 0)->throw()->json('items', []);

                if (! is_array($items)) {
                    continue;
                }

                foreach ($items as $index => $item) {
                    $artist = $this->upsertArtist($item);

                    if ($artist === null) {
                        continue;
                    }

                    UserTopArtist::query()->updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'artist_model_id' => $artist->id,
                            'time_range' => $timeRange,
                        ],
                        [
                            'rank' => $index + 1,
                            'score' => max(1, 50 - $index),
                            'synced_at' => now(),
                        ],
                    );
                }
            }

            $this->finishRun($run, 'completed');
        } catch (\Throwable $e) {
            $this->finishRun($run, 'failed', $e->getMessage());
            Log::channel('spotify')->warning('Spotify syncTopArtists failed', ['error' => $e->getMessage()]);
        }
    }

    public function syncTopTracks(User $user): void
    {
        $run = $this->startRun($user, 'top_tracks');

        try {
            $client = $this->client($user);

            foreach (['short_term', 'medium_term', 'long_term'] as $timeRange) {
                $items = $client->topTracksPage($timeRange, 50, 0)->throw()->json('items', []);

                if (! is_array($items)) {
                    continue;
                }

                foreach ($items as $index => $item) {
                    $track = $this->upsertTrack($item);

                    if ($track === null) {
                        continue;
                    }

                    UserTopTrack::query()->updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'track_id' => $track->id,
                            'time_range' => $timeRange,
                        ],
                        [
                            'rank' => $index + 1,
                            'score' => max(1, 50 - $index),
                            'synced_at' => now(),
                        ],
                    );

                    $this->syncTrackArtists($item, $track->id);
                }
            }

            $this->finishRun($run, 'completed');
        } catch (\Throwable $e) {
            $this->finishRun($run, 'failed', $e->getMessage());
            Log::channel('spotify')->warning('Spotify syncTopTracks failed', ['error' => $e->getMessage()]);
        }
    }

    public function syncRecentPlays(User $user): void
    {
        $run = $this->startRun($user, 'recent_plays');

        try {
            $client = $this->client($user);
            $items = $client->recentlyPlayed(50)->throw()->json('items', []);

            if (is_array($items)) {
                foreach ($items as $item) {
                    $trackPayload = data_get($item, 'track');

                    if (! is_array($trackPayload)) {
                        continue;
                    }

                    $track = $this->upsertTrack($trackPayload);

                    if ($track === null) {
                        continue;
                    }

                    $playedAt = data_get($item, 'played_at');

                    if (! is_string($playedAt) || $playedAt === '') {
                        continue;
                    }

                    UserRecentPlay::query()->updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'track_id' => $track->id,
                            'played_at' => $playedAt,
                        ],
                        [],
                    );

                    $this->syncTrackArtists($trackPayload, $track->id);
                }
            }

            $this->finishRun($run, 'completed');
        } catch (\Throwable $e) {
            $this->finishRun($run, 'failed', $e->getMessage());
            Log::channel('spotify')->warning('Spotify syncRecentPlays failed', ['error' => $e->getMessage()]);
        }
    }

    private function client(User $user): SpotifyStatsClient
    {
        return new SpotifyStatsClient($this->tokenService->ensureFreshToken($user));
    }

    private function upsertArtist(array $payload): ?Artist
    {
        $spotifyId = data_get($payload, 'id');
        $name = data_get($payload, 'name');

        if (! is_string($spotifyId) || $spotifyId === '' || ! is_string($name) || $name === '') {
            return null;
        }

        return Artist::query()->updateOrCreate(
            ['artist_id' => $spotifyId],
            [
                'artist_name' => $name,
                'genres' => is_array(data_get($payload, 'genres')) ? data_get($payload, 'genres') : [],
                'fetched_at' => now(),
                'expires_at' => now()->addDays(7),
            ],
        );
    }

    private function upsertTrack(array $payload): ?Track
    {
        $spotifyId = data_get($payload, 'id');
        $name = data_get($payload, 'name');

        if (! is_string($spotifyId) || $spotifyId === '' || ! is_string($name) || $name === '') {
            return null;
        }

        $albumSpotifyId = data_get($payload, 'album.id');
        $albumName = data_get($payload, 'album.name');
        $albumId = null;

        if (is_string($albumSpotifyId) && $albumSpotifyId !== '' && is_string($albumName) && $albumName !== '') {
            $album = Album::query()->updateOrCreate(
                ['spotify_id' => $albumSpotifyId],
                [
                    'name' => $albumName,
                    'album_type' => data_get($payload, 'album.album_type'),
                    'release_date' => data_get($payload, 'album.release_date'),
                    'images' => is_array(data_get($payload, 'album.images')) ? data_get($payload, 'album.images') : null,
                    'total_tracks' => (int) data_get($payload, 'album.total_tracks', 0),
                    'metadata_synced_at' => now(),
                ],
            );

            $albumId = $album->id;
        }

        return Track::query()->updateOrCreate(
            ['spotify_id' => $spotifyId],
            [
                'album_id' => $albumId,
                'name' => $name,
                'duration_ms' => (int) data_get($payload, 'duration_ms', 0),
                'explicit' => (bool) data_get($payload, 'explicit', false),
                'metadata_synced_at' => now(),
            ],
        );
    }

    private function syncTrackArtists(array $trackPayload, int $trackId): void
    {
        $artists = data_get($trackPayload, 'artists', []);

        if (! is_array($artists)) {
            return;
        }

        foreach ($artists as $artistPayload) {
            if (! is_array($artistPayload)) {
                continue;
            }

            $artist = $this->upsertArtist($artistPayload);

            if ($artist === null) {
                continue;
            }

            DB::table('artist_track')->updateOrInsert(
                [
                    'artist_model_id' => $artist->id,
                    'track_id' => $trackId,
                ],
                [
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }

    private function startRun(User $user, string $type): SpotifySyncRun
    {
        return SpotifySyncRun::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    private function finishRun(SpotifySyncRun $run, string $status, ?string $error = null): void
    {
        $run->update([
            'status' => $status,
            'finished_at' => now(),
            'error' => $error,
        ]);
    }
}
