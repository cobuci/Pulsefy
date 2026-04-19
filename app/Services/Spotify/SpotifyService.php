<?php

namespace App\Services\Spotify;

use App\Models\Artist;
use App\Models\Track;
use App\Models\User;
use App\Models\UserRecentPlay;
use App\Models\UserTopArtist;
use App\Models\UserTopTrack;
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

    private const array TOP_RANGE_FRESH_HOURS = [
        'short_term' => 6,
        'medium_term' => 24,
        'long_term' => 72,
    ];

    private const int RECENT_PLAYS_FRESH_MINUTES = 15;

    public function __construct(
        private SpotifyTokenService $tokenService,
        private ArtistGenreCacheService $artistGenreCache,
    ) {}

    public function topTracks(User $user, string $timeRange = 'medium_term'): array
    {
        $dbTracks = $this->topTracksFromDatabase($user, $timeRange);

        if ($dbTracks !== []) {
            return $dbTracks;
        }

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
        $dbArtists = $this->topArtistsFromDatabase($user, $timeRange);

        if ($dbArtists !== []) {
            return $dbArtists;
        }

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
        $dbPlays = $this->recentlyPlayedFromDatabase($user);

        if ($dbPlays !== []) {
            return $dbPlays;
        }

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
        $snapshot = [];

        foreach (['short_term', 'medium_term', 'long_term'] as $timeRange) {
            $tracks = $this->topTracksFromDatabase($user, $timeRange);
            $artists = $this->topArtistsFromDatabase($user, $timeRange);

            if ($tracks === [] || $artists === []) {
                continue;
            }

            $snapshot[$timeRange] = [
                'tracks' => $tracks,
                'artists' => $artists,
            ];
        }

        if (count($snapshot) > 0) {
            return $snapshot;
        }

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

    /**
     * @return SpotifyPayloadList
     */
    private function topTracksFromDatabase(User $user, string $timeRange): array
    {
        if (! $this->hasFreshTopTracksSnapshot($user, $timeRange)) {
            return [];
        }

        try {
            return UserTopTrack::query()
                ->whereBelongsTo($user)
                ->where('time_range', $timeRange)
                ->with([
                    'track.album',
                    'track.artists',
                ])
                ->orderBy('rank')
                ->get()
                ->pluck('track')
                ->filter(fn (mixed $track): bool => $track instanceof Track)
                ->map(fn (Track $track): array => $this->mapTrackToSpotifyPayload($track))
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::channel('spotify')->warning('DB top tracks snapshot unavailable', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return SpotifyPayloadList
     */
    private function topArtistsFromDatabase(User $user, string $timeRange): array
    {
        if (! $this->hasFreshTopArtistsSnapshot($user, $timeRange)) {
            return [];
        }

        try {
            return UserTopArtist::query()
                ->whereBelongsTo($user)
                ->where('time_range', $timeRange)
                ->with('artist.tracks.album')
                ->orderBy('rank')
                ->get()
                ->pluck('artist')
                ->filter(fn (mixed $artist): bool => $artist instanceof Artist)
                ->map(fn (Artist $artist): array => $this->mapArtistToSpotifyPayload($artist))
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::channel('spotify')->warning('DB top artists snapshot unavailable', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentlyPlayedFromDatabase(User $user): array
    {
        if (! $this->hasFreshRecentPlaysSnapshot($user)) {
            return [];
        }

        try {
            return UserRecentPlay::query()
                ->whereBelongsTo($user)
                ->with([
                    'track.album',
                    'track.artists',
                ])
                ->orderByDesc('played_at')
                ->limit(300)
                ->get()
                ->filter(fn (UserRecentPlay $play): bool => $play->track instanceof Track)
                ->map(function (UserRecentPlay $play): array {
                    /** @var Track $track */
                    $track = $play->track;

                    return [
                        'track' => $this->mapTrackToSpotifyPayload($track),
                        'played_at' => $play->played_at->toIso8601String(),
                        'context' => null,
                    ];
                })
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::channel('spotify')->warning('DB recent plays snapshot unavailable', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function hasFreshTopTracksSnapshot(User $user, string $timeRange): bool
    {
        $hours = self::TOP_RANGE_FRESH_HOURS[$timeRange] ?? self::TOP_RANGE_FRESH_HOURS['medium_term'];
        $freshSince = now()->subHours($hours);

        try {
            return UserTopTrack::query()
                ->whereBelongsTo($user)
                ->where('time_range', $timeRange)
                ->where('synced_at', '>=', $freshSince)
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasFreshTopArtistsSnapshot(User $user, string $timeRange): bool
    {
        $hours = self::TOP_RANGE_FRESH_HOURS[$timeRange] ?? self::TOP_RANGE_FRESH_HOURS['medium_term'];
        $freshSince = now()->subHours($hours);

        try {
            return UserTopArtist::query()
                ->whereBelongsTo($user)
                ->where('time_range', $timeRange)
                ->where('synced_at', '>=', $freshSince)
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasFreshRecentPlaysSnapshot(User $user): bool
    {
        try {
            return UserRecentPlay::query()
                ->whereBelongsTo($user)
                ->where('created_at', '>=', now()->subMinutes(self::RECENT_PLAYS_FRESH_MINUTES))
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTrackToSpotifyPayload(Track $track): array
    {
        $album = $track->album;

        return [
            'id' => $track->spotify_id,
            'name' => $track->name,
            'artists' => $track->artists
                ->map(fn (Artist $artist): array => [
                    'id' => $artist->artist_id,
                    'name' => $artist->artist_name ?? 'Unknown Artist',
                    'external_urls' => [
                        'spotify' => 'https://open.spotify.com/artist/'.$artist->artist_id,
                    ],
                ])
                ->values()
                ->all(),
            'album' => [
                'id' => $album?->spotify_id ?? '',
                'name' => $album?->name ?? 'Unknown Album',
                'images' => $album?->images ?? [],
                'release_date' => $album?->release_date ?? '',
                'album_type' => $album?->album_type,
                'total_tracks' => $album?->total_tracks,
                'external_urls' => [
                    'spotify' => $album?->spotify_id
                        ? 'https://open.spotify.com/album/'.$album->spotify_id
                        : '',
                ],
            ],
            'duration_ms' => $track->duration_ms,
            'popularity' => 0,
            'preview_url' => null,
            'external_urls' => [
                'spotify' => 'https://open.spotify.com/track/'.$track->spotify_id,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapArtistToSpotifyPayload(Artist $artist): array
    {
        $images = is_array($artist->images) ? $artist->images : [];

        if ($images === []) {
            $images = $this->fallbackArtistImagesFromTracks($artist);
        }

        return [
            'id' => $artist->artist_id,
            'name' => $artist->artist_name ?? 'Unknown Artist',
            'images' => $images,
            'genres' => $artist->genres,
            'popularity' => $artist->popularity ?? 0,
            'uri' => $artist->uri ?? 'spotify:artist:'.$artist->artist_id,
            'external_urls' => $artist->external_urls ?? [
                'spotify' => 'https://open.spotify.com/artist/'.$artist->artist_id,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fallbackArtistImagesFromTracks(Artist $artist): array
    {
        $trackWithAlbumArt = $artist->tracks
            ->first(fn (Track $track): bool => is_array($track->album?->images) && $track->album->images !== []);

        if (! $trackWithAlbumArt?->album?->images || ! is_array($trackWithAlbumArt->album->images)) {
            return [];
        }

        return $trackWithAlbumArt->album->images;
    }
}
