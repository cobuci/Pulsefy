<?php

namespace App\Services\Spotify\Library;

use App\Models\Playlist;
use App\Models\PlaylistTrack;
use App\Models\Track;
use App\Models\User;
use App\Services\Spotify\Client\SpotifyPlaylistClient;
use App\Services\Spotify\SpotifyTokenService;
use App\Services\Spotify\Sync\SpotifyCatalogHydrationService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SpotifyLibraryService
{
    private const int PLAYLIST_TTL_MINUTES = 45;

    private const int POSITION_STEP = 100;

    public function __construct(
        private readonly SpotifyTokenService $tokenService,
        private readonly ?SpotifyCatalogHydrationService $catalogHydration = null,
    ) {}

    public function syncUserPlaylists(User $user): int
    {
        $payload = [];
        $offset = 0;
        $limit = 50;

        while (true) {
            $response = $this->clientFor($user)->currentUserPlaylists($limit, $offset);

            if (! $response->successful()) {
                Log::channel('spotify')->warning('Could not sync user playlists', [
                    'user_id' => $user->id,
                    'status' => $response->status(),
                ]);

                break;
            }

            $items = Arr::wrap($response->json('items'));

            if ($items === []) {
                break;
            }

            $payload = [...$payload, ...$items];

            if ($response->json('next') === null) {
                break;
            }

            $offset += $limit;
        }

        foreach ($payload as $playlist) {
            $this->upsertPlaylist($user, Arr::wrap($playlist));
        }

        return count($payload);
    }

    public function syncPlaylistTracks(User $user, Playlist $playlist): void
    {
        if ($playlist->user_id !== $user->id) {
            abort(403);
        }

        $client = $this->clientFor($user);
        $offset = 0;
        $limit = 50;
        $rows = [];
        $trackPayloads = [];
        $hadSuccessfulResponse = false;

        while (true) {
            $response = $this->playlistTracksWithFallback($client, $playlist->spotify_id, $limit, $offset);

            if (! $response->successful()) {
                Log::channel('spotify')->warning('Could not sync playlist tracks', [
                    'playlist_id' => $playlist->id,
                    'spotify_id' => $playlist->spotify_id,
                    'status' => $response->status(),
                ]);

                break;
            }

            $hadSuccessfulResponse = true;

            $items = Arr::wrap($response->json('items'));

            if ($items === []) {
                break;
            }

            foreach ($items as $position => $item) {
                $trackPayload = data_get($item, 'item');

                if (! is_array($trackPayload)) {
                    $trackPayload = data_get($item, 'track');
                }

                $trackSpotifyId = (string) data_get($trackPayload, 'id', '');

                if ($trackSpotifyId === '') {
                    continue;
                }

                if (is_array($trackPayload) && $trackPayload !== []) {
                    $trackPayloads[] = $trackPayload;
                }

                $rows[] = [
                    'playlist_id' => $playlist->id,
                    'track_id' => null,
                    'spotify_track_id' => $trackSpotifyId,
                    'position' => $offset + $position,
                    'added_at' => $this->parseSpotifyTimestamp(data_get($item, 'added_at')),
                    'added_by_spotify_id' => data_get($item, 'added_by.id'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($response->json('next') === null) {
                break;
            }

            $offset += $limit;
        }

        if (! $hadSuccessfulResponse) {
            return;
        }

        if ($trackPayloads !== []) {
            ($this->catalogHydration ?? app(SpotifyCatalogHydrationService::class))->hydrateTracks($trackPayloads);
        }

        DB::transaction(function () use ($playlist, $rows): void {
            PlaylistTrack::query()->whereBelongsTo($playlist)->delete();

            if ($rows === []) {
                return;
            }

            $trackIdsBySpotifyId = Track::query()
                ->whereIn('spotify_id', collect($rows)->pluck('spotify_track_id')->values()->all())
                ->pluck('id', 'spotify_id');

            $rows = array_map(function (array $row) use ($trackIdsBySpotifyId): array {
                $resolvedTrackId = $trackIdsBySpotifyId->get($row['spotify_track_id']);

                if (is_int($resolvedTrackId)) {
                    $row['track_id'] = $resolvedTrackId;
                }

                return $row;
            }, $rows);

            PlaylistTrack::query()->insert($rows);
        });

        $playlist->update([
            'tracks_total' => count($rows),
            'synced_at' => now(),
            'expires_at' => now()->addMinutes(self::PLAYLIST_TTL_MINUTES),
        ]);
    }

    public function syncLikedTracks(User $user): void
    {
        $client = $this->clientFor($user);

        $playlist = Playlist::query()->firstOrCreate(
            ['user_id' => $user->id, 'spotify_id' => 'liked-songs'],
            [
                'name' => 'Liked Songs',
                'is_liked_playlist' => true,
                'is_public' => false,
                'is_collaborative' => false,
                'tracks_total' => 0,
                'position' => 0,
            ],
        );

        $fingerprintResponse = $client->savedTracks(1, 0);

        if (! $fingerprintResponse->successful()) {
            Log::channel('spotify')->warning('Could not fetch liked songs fingerprint', [
                'user_id' => $user->id,
                'status' => $fingerprintResponse->status(),
            ]);

            return;
        }

        $apiTotal = (int) $fingerprintResponse->json('total');
        $apiFirstId = (string) data_get($fingerprintResponse->json('items.0'), 'track.id', '');
        $apiFirstAddedAt = (string) data_get($fingerprintResponse->json('items.0'), 'added_at', '');

        $dbTotal = (int) $playlist->tracks_total;
        $dbFirstTrack = PlaylistTrack::query()
            ->whereBelongsTo($playlist)
            ->orderBy('position')
            ->first(['spotify_track_id', 'added_at']);

        $dbFirstId = $dbFirstTrack ? (string) $dbFirstTrack->spotify_track_id : '';
        $dbFirstAddedAt = $dbFirstTrack ? (string) $dbFirstTrack->added_at : '';

        $firstItemSame = $apiFirstId === $dbFirstId && $apiFirstAddedAt === $dbFirstAddedAt;

        if ($apiTotal === $dbTotal && $firstItemSame) {
            return;
        }

        $isIncrementalAddition = $apiTotal > $dbTotal && $firstItemSame === false && $dbTotal > 0;

        if ($isIncrementalAddition) {
            $this->syncLikedTracksIncremental($user, $client, $playlist, $apiTotal, $dbTotal, $apiFirstId, $apiFirstAddedAt);
        } else {
            $this->syncLikedTracksFull($user, $client, $playlist);
        }
    }

    private function syncLikedTracksFull(User $user, SpotifyPlaylistClient $client, Playlist $playlist): void
    {
        $offset = 0;
        $limit = 50;
        $rows = [];
        $trackPayloads = [];
        $hadSuccessfulResponse = false;

        while (true) {
            $response = $client->savedTracks($limit, $offset);

            if (! $response->successful()) {
                Log::channel('spotify')->warning('Could not sync liked tracks (full)', [
                    'user_id' => $user->id,
                    'status' => $response->status(),
                ]);

                break;
            }

            $hadSuccessfulResponse = true;
            $items = Arr::wrap($response->json('items'));

            if ($items === []) {
                break;
            }

            foreach ($items as $position => $item) {
                $trackPayload = data_get($item, 'track');
                $trackSpotifyId = (string) data_get($trackPayload, 'id', '');

                if ($trackSpotifyId === '') {
                    continue;
                }

                if (is_array($trackPayload) && $trackPayload !== []) {
                    $trackPayloads[] = $trackPayload;
                }

                $rows[] = [
                    'playlist_id' => $playlist->id,
                    'track_id' => null,
                    'spotify_track_id' => $trackSpotifyId,
                    'position' => $offset + $position,
                    'added_at' => $this->parseSpotifyTimestamp(data_get($item, 'added_at')),
                    'added_by_spotify_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($response->json('next') === null) {
                break;
            }

            $offset += $limit;
        }

        if (! $hadSuccessfulResponse) {
            return;
        }

        $this->persistLikedTracks($playlist, $rows, $trackPayloads);
    }

    private function syncLikedTracksIncremental(
        User $user,
        SpotifyPlaylistClient $client,
        Playlist $playlist,
        int $apiTotal,
        int $dbTotal,
        string $apiFirstId,
        string $apiFirstAddedAt,
    ): void {
        $existingKeys = PlaylistTrack::query()
            ->whereBelongsTo($playlist)
            ->get(['spotify_track_id', 'added_at'])
            ->mapWithKeys(fn ($row): array => [$row->spotify_track_id.'|'.$row->added_at => true])
            ->all();

        $newRows = [];
        $newTrackPayloads = [];
        $offset = 0;
        $limit = 50;
        $foundOverlap = false;

        while (! $foundOverlap) {
            $response = $client->savedTracks($limit, $offset);

            if (! $response->successful()) {
                Log::channel('spotify')->warning('Could not sync liked tracks (incremental)', [
                    'user_id' => $user->id,
                    'status' => $response->status(),
                ]);

                return;
            }

            $items = Arr::wrap($response->json('items'));

            if ($items === []) {
                break;
            }

            foreach ($items as $item) {
                $trackPayload = data_get($item, 'track');
                $trackSpotifyId = (string) data_get($trackPayload, 'id', '');
                $addedAt = $this->parseSpotifyTimestamp(data_get($item, 'added_at'));
                $key = $trackSpotifyId.'|'.$addedAt;

                if (isset($existingKeys[$key])) {
                    $foundOverlap = true;

                    break;
                }

                if ($trackSpotifyId === '') {
                    continue;
                }

                if (is_array($trackPayload) && $trackPayload !== []) {
                    $newTrackPayloads[] = $trackPayload;
                }

                $newRows[] = [
                    'playlist_id' => $playlist->id,
                    'track_id' => null,
                    'spotify_track_id' => $trackSpotifyId,
                    'position' => count($newRows),
                    'added_at' => $addedAt,
                    'added_by_spotify_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($response->json('next') === null) {
                break;
            }

            $offset += $limit;
        }

        if ($newRows === []) {
            return;
        }

        if ($newTrackPayloads !== []) {
            ($this->catalogHydration ?? app(SpotifyCatalogHydrationService::class))->hydrateTracks($newTrackPayloads);
        }

        $newCount = count($newRows);

        DB::transaction(function () use ($playlist, $newRows, $newCount): void {
            PlaylistTrack::query()
                ->whereBelongsTo($playlist)
                ->increment('position', $newCount);

            $trackIdsBySpotifyId = Track::query()
                ->whereIn('spotify_id', collect($newRows)->pluck('spotify_track_id')->values()->all())
                ->pluck('id', 'spotify_id');

            $newRows = array_map(function (array $row) use ($trackIdsBySpotifyId): array {
                $resolvedTrackId = $trackIdsBySpotifyId->get($row['spotify_track_id']);

                if (is_int($resolvedTrackId)) {
                    $row['track_id'] = $resolvedTrackId;
                }

                return $row;
            }, $newRows);

            PlaylistTrack::query()->insert($newRows);
        });

        $playlist->update([
            'tracks_total' => $dbTotal + $newCount,
            'synced_at' => now(),
            'expires_at' => now()->addMinutes(self::PLAYLIST_TTL_MINUTES),
        ]);
    }

    private function persistLikedTracks(Playlist $playlist, array $rows, array $trackPayloads): void
    {
        if ($trackPayloads !== []) {
            ($this->catalogHydration ?? app(SpotifyCatalogHydrationService::class))->hydrateTracks($trackPayloads);
        }

        DB::transaction(function () use ($playlist, $rows): void {
            PlaylistTrack::query()->whereBelongsTo($playlist)->delete();

            if ($rows === []) {
                return;
            }

            $trackIdsBySpotifyId = Track::query()
                ->whereIn('spotify_id', collect($rows)->pluck('spotify_track_id')->values()->all())
                ->pluck('id', 'spotify_id');

            $rows = array_map(function (array $row) use ($trackIdsBySpotifyId): array {
                $resolvedTrackId = $trackIdsBySpotifyId->get($row['spotify_track_id']);

                if (is_int($resolvedTrackId)) {
                    $row['track_id'] = $resolvedTrackId;
                }

                return $row;
            }, $rows);

            PlaylistTrack::query()->insert($rows);
        });

        $playlist->update([
            'tracks_total' => count($rows),
            'synced_at' => now(),
            'expires_at' => now()->addMinutes(self::PLAYLIST_TTL_MINUTES),
        ]);
    }

    private function upsertPlaylist(User $user, array $payload): Playlist
    {
        $spotifyId = (string) data_get($payload, 'id', '');
        $name = (string) data_get($payload, 'name', '');

        if ($spotifyId === '' || $name === '') {
            abort(422, 'Invalid playlist payload.');
        }

        $playlist = Playlist::query()->firstOrNew([
            'user_id' => $user->id,
            'spotify_id' => $spotifyId,
        ]);

        $playlist->name = $name;
        $playlist->description = data_get($payload, 'description');
        $playlist->images = is_array(data_get($payload, 'images')) ? data_get($payload, 'images') : ($playlist->images ?? null);
        $playlist->owner_spotify_id = data_get($payload, 'owner.id');
        $playlist->owner_name = data_get($payload, 'owner.display_name');
        $playlist->is_public = (bool) data_get($payload, 'public', false);
        $playlist->is_collaborative = (bool) data_get($payload, 'collaborative', false);
        $playlist->tracks_total = (int) data_get($payload, 'tracks.total', 0);
        $playlist->snapshot_id = data_get($payload, 'snapshot_id');
        $playlist->uri = data_get($payload, 'uri');
        $playlist->external_urls = is_array(data_get($payload, 'external_urls'))
            ? data_get($payload, 'external_urls')
            : ($playlist->external_urls ?? null);
        $playlist->synced_at = now();
        $playlist->expires_at = now()->addMinutes(self::PLAYLIST_TTL_MINUTES);

        $playlist->save();

        if (Schema::hasColumn('playlists', 'position') && (! is_int($playlist->position) || $playlist->position <= 0)) {
            $playlist->position = $playlist->id * self::POSITION_STEP;
            $playlist->save();
        }

        return $playlist;
    }

    private function clientFor(User $user): SpotifyPlaylistClient
    {
        return new SpotifyPlaylistClient($this->tokenService->ensureFreshToken($user));
    }

    private function playlistTracksWithFallback(
        SpotifyPlaylistClient $client,
        string $playlistSpotifyId,
        int $limit,
        int $offset,
    ): Response {
        $withTokenMarket = $client->playlistTracks($playlistSpotifyId, $limit, $offset, 'from_token');

        if ($withTokenMarket->status() !== 403) {
            return $withTokenMarket;
        }

        $withoutMarket = $client->playlistTracks($playlistSpotifyId, $limit, $offset, null);

        if ($withoutMarket->status() !== 403) {
            Log::channel('spotify')->notice('Playlist tracks fallback without market succeeded', [
                'spotify_id' => $playlistSpotifyId,
                'offset' => $offset,
                'limit' => $limit,
            ]);

            return $withoutMarket;
        }

        return $withTokenMarket;
    }

    private function parseSpotifyTimestamp(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }
}
