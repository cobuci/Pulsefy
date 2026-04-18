<?php

namespace App\Services\Spotify\Sync;

use App\Events\Spotify\SyncStatusUpdated;
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
        SyncStatusUpdated::dispatch($user->id);

        $run = $this->startRun($user, 'top_artists');
        $meta = [
            'fetched' => 0,
            'upserted' => 0,
            'skipped' => 0,
            'pruned' => 0,
            'ranges' => [],
        ];

        try {
            $client = $this->client($user);

            foreach (['short_term', 'medium_term', 'long_term'] as $timeRange) {
                $items = $client->topArtistsPage($timeRange, 50, 0)->throw()->json('items', []);

                if (! is_array($items)) {
                    $meta['ranges'][$timeRange] = [
                        'fetched' => 0,
                        'upserted' => 0,
                        'skipped' => 0,
                        'pruned' => 0,
                    ];

                    continue;
                }

                $rangeMeta = [
                    'fetched' => count($items),
                    'upserted' => 0,
                    'skipped' => 0,
                    'pruned' => 0,
                ];

                foreach ($items as $index => $item) {
                    $artist = $this->upsertArtist($item);

                    if ($artist === null) {
                        $rangeMeta['skipped']++;

                        continue;
                    }

                    $rangeMeta['upserted']++;

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

                $currentArtistIds = $this->existingArtistModelIds($items);

                $staleQuery = UserTopArtist::query()
                    ->where('user_id', $user->id)
                    ->where('time_range', $timeRange);

                if ($currentArtistIds === []) {
                    $rangeMeta['pruned'] += $staleQuery->delete();

                    $meta['fetched'] += $rangeMeta['fetched'];
                    $meta['upserted'] += $rangeMeta['upserted'];
                    $meta['skipped'] += $rangeMeta['skipped'];
                    $meta['pruned'] += $rangeMeta['pruned'];
                    $meta['ranges'][$timeRange] = $rangeMeta;

                    continue;
                }

                $rangeMeta['pruned'] += $staleQuery
                    ->whereNotIn('artist_model_id', $currentArtistIds)
                    ->delete();

                $meta['fetched'] += $rangeMeta['fetched'];
                $meta['upserted'] += $rangeMeta['upserted'];
                $meta['skipped'] += $rangeMeta['skipped'];
                $meta['pruned'] += $rangeMeta['pruned'];
                $meta['ranges'][$timeRange] = $rangeMeta;
            }

            $this->finishRun($run, 'completed', meta: $meta);
            SyncStatusUpdated::dispatch($user->id);
        } catch (\Throwable $e) {
            $meta['exception'] = $e::class;
            $this->finishRun($run, 'failed', $e->getMessage(), $meta);
            SyncStatusUpdated::dispatch($user->id);
            Log::channel('spotify')->warning('Spotify syncTopArtists failed', ['error' => $e->getMessage()]);
        }
    }

    public function syncTopTracks(User $user): void
    {
        SyncStatusUpdated::dispatch($user->id);

        $run = $this->startRun($user, 'top_tracks');
        $meta = [
            'fetched' => 0,
            'upserted' => 0,
            'skipped' => 0,
            'pruned' => 0,
            'ranges' => [],
        ];

        try {
            $client = $this->client($user);

            foreach (['short_term', 'medium_term', 'long_term'] as $timeRange) {
                $items = $client->topTracksPage($timeRange, 50, 0)->throw()->json('items', []);

                if (! is_array($items)) {
                    $meta['ranges'][$timeRange] = [
                        'fetched' => 0,
                        'upserted' => 0,
                        'skipped' => 0,
                        'pruned' => 0,
                    ];

                    continue;
                }

                $rangeMeta = [
                    'fetched' => count($items),
                    'upserted' => 0,
                    'skipped' => 0,
                    'pruned' => 0,
                ];

                foreach ($items as $index => $item) {
                    $track = $this->upsertTrack($item);

                    if ($track === null) {
                        $rangeMeta['skipped']++;

                        continue;
                    }

                    $rangeMeta['upserted']++;

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

                $currentTrackIds = $this->existingTrackIds($items);

                $staleQuery = UserTopTrack::query()
                    ->where('user_id', $user->id)
                    ->where('time_range', $timeRange);

                if ($currentTrackIds === []) {
                    $rangeMeta['pruned'] += $staleQuery->delete();

                    $meta['fetched'] += $rangeMeta['fetched'];
                    $meta['upserted'] += $rangeMeta['upserted'];
                    $meta['skipped'] += $rangeMeta['skipped'];
                    $meta['pruned'] += $rangeMeta['pruned'];
                    $meta['ranges'][$timeRange] = $rangeMeta;

                    continue;
                }

                $rangeMeta['pruned'] += $staleQuery
                    ->whereNotIn('track_id', $currentTrackIds)
                    ->delete();

                $meta['fetched'] += $rangeMeta['fetched'];
                $meta['upserted'] += $rangeMeta['upserted'];
                $meta['skipped'] += $rangeMeta['skipped'];
                $meta['pruned'] += $rangeMeta['pruned'];
                $meta['ranges'][$timeRange] = $rangeMeta;
            }

            $this->finishRun($run, 'completed', meta: $meta);
            SyncStatusUpdated::dispatch($user->id);
        } catch (\Throwable $e) {
            $meta['exception'] = $e::class;
            $this->finishRun($run, 'failed', $e->getMessage(), $meta);
            SyncStatusUpdated::dispatch($user->id);
            Log::channel('spotify')->warning('Spotify syncTopTracks failed', ['error' => $e->getMessage()]);
        }
    }

    public function syncRecentPlays(User $user): void
    {
        SyncStatusUpdated::dispatch($user->id);

        $run = $this->startRun($user, 'recent_plays');
        $meta = [
            'fetched' => 0,
            'upserted' => 0,
            'skipped' => 0,
        ];

        try {
            $client = $this->client($user);
            $items = $client->recentlyPlayed(50)->throw()->json('items', []);

            if (is_array($items)) {
                $meta['fetched'] = count($items);

                foreach ($items as $item) {
                    $trackPayload = data_get($item, 'track');

                    if (! is_array($trackPayload)) {
                        $meta['skipped']++;

                        continue;
                    }

                    $track = $this->upsertTrack($trackPayload);

                    if ($track === null) {
                        $meta['skipped']++;

                        continue;
                    }

                    $playedAt = data_get($item, 'played_at');

                    if (! is_string($playedAt) || $playedAt === '') {
                        $meta['skipped']++;

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

                    $meta['upserted']++;

                    $this->syncTrackArtists($trackPayload, $track->id);
                }
            }

            $this->finishRun($run, 'completed', meta: $meta);
            SyncStatusUpdated::dispatch($user->id);
        } catch (\Throwable $e) {
            $meta['exception'] = $e::class;
            $this->finishRun($run, 'failed', $e->getMessage(), $meta);
            SyncStatusUpdated::dispatch($user->id);
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
                'images' => is_array(data_get($payload, 'images')) ? data_get($payload, 'images') : null,
                'popularity' => is_numeric(data_get($payload, 'popularity')) ? (int) data_get($payload, 'popularity') : null,
                'uri' => is_string(data_get($payload, 'uri')) ? data_get($payload, 'uri') : null,
                'external_urls' => is_array(data_get($payload, 'external_urls')) ? data_get($payload, 'external_urls') : null,
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

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, int>
     */
    private function existingArtistModelIds(array $items): array
    {
        $spotifyIds = collect($items)
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->values()
            ->all();

        if ($spotifyIds === []) {
            return [];
        }

        return Artist::query()
            ->whereIn('artist_id', $spotifyIds)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, int>
     */
    private function existingTrackIds(array $items): array
    {
        $spotifyIds = collect($items)
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->values()
            ->all();

        if ($spotifyIds === []) {
            return [];
        }

        return Track::query()
            ->whereIn('spotify_id', $spotifyIds)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
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

    /**
     * @param  array<string, mixed>  $meta
     */
    private function finishRun(SpotifySyncRun $run, string $status, ?string $error = null, array $meta = []): void
    {
        $durationMs = $run->started_at?->diffInMilliseconds(now());

        if ($durationMs !== null) {
            $meta['duration_ms'] = $durationMs;
        }

        $run->update([
            'status' => $status,
            'finished_at' => now(),
            'error' => $error,
            'meta' => $meta,
        ]);
    }
}
