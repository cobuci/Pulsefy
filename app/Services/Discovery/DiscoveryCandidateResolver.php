<?php

namespace App\Services\Discovery;

use App\Models\SimilarityCache;
use App\Models\User;
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

    public function __construct(
        private readonly LastFmClient $lastFm,
        private readonly GeminiRecommendationResolver $geminiResolver,
    ) {}

    /**
     * @param  array<string, float>  $affinityMap
     * @param  Collection<int, UserTopTrack>  $topTracks
     * @param  array<string, mixed>  $exclusionSet
     * @param  Collection<int, UserRecentPlay>  $recentPlays
     * @return array<string, array<string, mixed>>
     */
    public function resolve(
        User $user,
        array $affinityMap,
        Collection $topTracks,
        array $exclusionSet,
        Collection $recentPlays,
    ): array {
        $artistSeeds = array_slice($affinityMap, 0, 10, true);
        $similarArtists = $this->artistSimilarity(array_keys($artistSeeds));

        $topTrackNames = $topTracks
            ->sortByDesc(fn (UserTopTrack $t) => $this->trackSeedScore($t))
            ->take(10)
            ->map(fn (UserTopTrack $t) => $t->track->name)
            ->filter()
            ->values()
            ->all();

        $candidates = $this->geminiResolver->resolve(
            user: $user,
            affinityMap: $affinityMap,
            similarArtists: $similarArtists,
            topTrackNames: $topTrackNames,
            exclusionSet: $exclusionSet,
        );

        foreach ($candidates as $id => $candidate) {
            $candidates[$id]['recent_play_days_ago'] = $this->recentPlayDaysAgo($id, $recentPlays);
        }

        return $candidates;
    }

    /** @param string[] $artistNames
     *  @return array<string, float> */
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

    /** @return array<string, mixed> */
    private function fetchAndCacheArtistSimilarity(string $name): array
    {
        $payload = $this->lastFm->artistSimilar($name);
        SimilarityCache::updateOrCreate(
            ['type' => 'artist', 'key' => $name],
            ['payload' => $payload, 'fetched_at' => now(), 'expires_at' => now()->addDays(self::SIMILARITY_CACHE_TTL_DAYS)],
        );

        return $payload;
    }

    /** @param Collection<int, UserRecentPlay> $recentPlays */
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
