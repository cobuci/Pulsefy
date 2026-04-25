<?php

namespace App\Services\Discovery;

use App\Models\Artist;
use App\Models\SimilarityCache;
use App\Models\Track;
use App\Models\UserRecentPlay;
use App\Models\UserTopTrack;
use App\Services\LastFm\LastFmClient;
use Illuminate\Database\Eloquent\Collection;

final class DiscoveryCandidateResolver
{
    private const int SIMILARITY_CACHE_TTL_DAYS = 30;

    private const array TIME_RANGE_WEIGHTS = [
        'short_term' => 3.0,
        'medium_term' => 2.0,
        'long_term' => 1.0,
    ];

    private const int SUPPRESSION_WINDOW_DAYS = 14;

    public function __construct(
        private readonly LastFmClient $lastFm,
    ) {}

    /**
     * @param  array<string, float>  $affinityMap
     * @param  Collection<int, UserTopTrack>  $topTracks
     * @param  array<string, mixed>  $exclusionSet
     * @param  Collection<int, UserRecentPlay>  $recentPlays
     * @return array<string, array<string, mixed>>
     */
    public function resolve(
        array $affinityMap,
        Collection $topTracks,
        array $exclusionSet,
        Collection $recentPlays,
    ): array {
        $artistSeeds = array_slice($affinityMap, 0, 10, true);
        $trackSeeds = $topTracks
            ->sortByDesc(fn (UserTopTrack $t) => $this->trackSeedScore($t))
            ->take(5)
            ->values();

        $candidates = [];

        foreach ($this->artistSimilarity(array_keys($artistSeeds)) as $artistName => $matchScore) {
            foreach ($this->dbTracksForArtist($artistName) as $track) {
                if (isset($exclusionSet[$track['spotify_id']])) {
                    continue;
                }
                $id = $track['spotify_id'];
                $existing = $candidates[$id] ?? null;
                $candidates[$id] = array_merge($track, [
                    'artist_affinity' => $affinityMap[$artistName] ?? 0.0,
                    'lastfm_match' => max((float) ($existing['lastfm_match'] ?? 0), (float) $matchScore),
                    'seed_track_match' => (float) ($existing['seed_track_match'] ?? 0),
                    'recent_play_days_ago' => $this->recentPlayDaysAgo($id, $recentPlays),
                ]);
            }
        }

        foreach ($this->trackSimilarity($trackSeeds->all()) as $trackName => $pair) {
            $artistName = $pair['artist'];
            foreach ($this->dbTracksForArtist($artistName) as $track) {
                if (isset($exclusionSet[$track['spotify_id']])) {
                    continue;
                }
                $id = $track['spotify_id'];
                $existing = $candidates[$id] ?? null;
                $candidates[$id] = array_merge($track, [
                    'artist_affinity' => $affinityMap[$artistName] ?? 0.0,
                    'lastfm_match' => (float) ($existing['lastfm_match'] ?? 0),
                    'seed_track_match' => max((float) ($existing['seed_track_match'] ?? 0), (float) $pair['match']),
                    'recent_play_days_ago' => $this->recentPlayDaysAgo($id, $recentPlays),
                ]);
            }
        }

        return $candidates;
    }

    /**
     * @param  string[]  $artistNames
     * @return array<string, float>
     */
    private function artistSimilarity(array $artistNames): array
    {
        if ($artistNames === []) {
            return [];
        }

        $cached = SimilarityCache::query()
            ->where('type', 'artist')
            ->whereIn('key', $artistNames)
            ->valid()
            ->get()
            ->keyBy('key');

        $candidates = [];

        foreach ($artistNames as $name) {
            $payload = $cached->has($name)
                ? $cached->get($name)->payload
                : $this->fetchAndCacheArtistSimilarity($name);

            foreach ($payload as $item) {
                $similar = (string) ($item['name'] ?? '');
                if ($similar === '') {
                    continue;
                }
                $candidates[$similar] = max($candidates[$similar] ?? 0.0, (float) ($item['match'] ?? 0));
            }
        }

        return $candidates;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchAndCacheArtistSimilarity(string $name): array
    {
        $payload = $this->lastFm->artistSimilar($name);

        SimilarityCache::updateOrCreate(
            ['type' => 'artist', 'key' => $name],
            ['payload' => $payload, 'fetched_at' => now(), 'expires_at' => now()->addDays(self::SIMILARITY_CACHE_TTL_DAYS)],
        );

        return $payload;
    }

    /**
     * @param  UserTopTrack[]  $trackSeeds
     * @return array<string, array{artist: string, match: float}>
     */
    private function trackSimilarity(array $trackSeeds): array
    {
        $candidates = [];

        foreach ($trackSeeds as $topTrack) {
            $track = $topTrack->track;
            $firstArtist = $track->artists->first();
            if ($firstArtist === null) {
                continue;
            }
            $artistName = (string) $firstArtist->artist_name;
            if ($artistName === '') {
                continue;
            }

            $cacheKey = $artistName.'||'.$track->name;
            $entry = SimilarityCache::query()->where('type', 'track')->where('key', $cacheKey)->valid()->first();

            $payload = $entry?->payload ?? $this->fetchAndCacheTrackSimilarity($artistName, $track->name, $cacheKey);

            foreach ($payload as $item) {
                $name = (string) ($item['name'] ?? '');
                $tArtist = (string) ($item['artist']['name'] ?? '');
                if ($name === '' || $tArtist === '') {
                    continue;
                }
                $match = (float) ($item['match'] ?? 0);
                if (! isset($candidates[$name]) || $match > $candidates[$name]['match']) {
                    $candidates[$name] = ['artist' => $tArtist, 'match' => $match];
                }
            }
        }

        return $candidates;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchAndCacheTrackSimilarity(string $artistName, string $trackName, string $cacheKey): array
    {
        $payload = $this->lastFm->trackSimilar($artistName, $trackName);

        SimilarityCache::updateOrCreate(
            ['type' => 'track', 'key' => $cacheKey],
            ['payload' => $payload, 'fetched_at' => now(), 'expires_at' => now()->addDays(self::SIMILARITY_CACHE_TTL_DAYS)],
        );

        return $payload;
    }

    /**
     * @return array<int, array{spotify_id: string, name: string, artist: string, album: string, image_url: string|null, preview_url: string|null}>
     */
    private function dbTracksForArtist(string $artistName): array
    {
        $artist = Artist::query()
            ->where('artist_name', $artistName)
            ->with(['tracks.album'])
            ->first();

        if ($artist === null) {
            return [];
        }

        return $artist->tracks->map(function (Track $track) use ($artistName): array {
            $images = $track->album?->images ?? [];
            $imageUrl = is_array($images) && isset($images[0]['#text'])
                ? (string) $images[0]['#text']
                : (is_array($images) && isset($images[0]['url']) ? (string) $images[0]['url'] : null);

            return [
                'spotify_id' => $track->spotify_id,
                'name' => $track->name,
                'artist' => $artistName,
                'album' => $track->album?->name ?? '',
                'image_url' => $imageUrl,
                'preview_url' => null,
            ];
        })->all();
    }

    /**
     * @param  Collection<int, UserRecentPlay>  $recentPlays
     */
    private function recentPlayDaysAgo(string $spotifyId, Collection $recentPlays): ?float
    {
        $play = $recentPlays->first(fn (UserRecentPlay $p) => $p->track->spotify_id === $spotifyId);

        return $play ? (float) $play->played_at->diffInDays(now()) : null;
    }

    private function trackSeedScore(UserTopTrack $topTrack): float
    {
        $weight = self::TIME_RANGE_WEIGHTS[$topTrack->time_range] ?? 1.0;

        return ($topTrack->score * $weight) / sqrt(max(1, $topTrack->rank));
    }
}
