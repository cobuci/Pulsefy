<?php

namespace App\Services\Discovery;

use App\Models\DailyRecommendation;
use App\Models\DiscoveryLikedTrack;
use App\Models\RecommendedTrack;
use App\Models\TrackInteraction;
use App\Models\User;
use App\Models\UserRecentPlay;
use App\Models\UserTopArtist;
use App\Models\UserTopTrack;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

final class DiscoveryService
{
    private const int MAX_CANDIDATES = 60;

    private const int MIN_RECOMMENDATIONS = 20;

    private const int MAX_RECOMMENDATIONS = 50;

    private const int SUPPRESSION_WINDOW_DAYS = 14;

    public function __construct(
        private readonly DiscoveryAffinityBuilder $affinityBuilder,
        private readonly DiscoveryCandidateResolver $candidateResolver,
        private readonly DiscoveryScorer $scorer,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function generate(User $user): array
    {
        $cacheKey = "discovery:{$user->id}:".now()->toDateString();
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $topArtists = UserTopArtist::query()->where('user_id', $user->id)->with('artist')->get();
        $topTracks = UserTopTrack::query()->where('user_id', $user->id)->with('track.artists')->get();
        $recentPlays = UserRecentPlay::query()->where('user_id', $user->id)->latest('played_at')->limit(200)->with('track.artists')->get();

        $exclusionSet = $this->buildExclusionSet($user->id, $recentPlays);
        $penalizedArtists = $this->buildPenalizedArtists($user->id);
        $affinityMap = $this->affinityBuilder->build($user, $topArtists, $recentPlays);
        $candidates = $this->candidateResolver->resolve($user, $affinityMap, $topTracks, $exclusionSet, $recentPlays);

        foreach ($candidates as $id => $candidate) {
            $candidates[$id]['match_score'] = $this->scorer->score($candidate, $penalizedArtists);
        }

        $recommendations = $this->selectAndShape($candidates, $user->id);

        if ($recommendations !== []) {
            $this->persist($user, $recommendations);
        }

        $ttl = now()->secondsUntilEndOfDay();
        Cache::put($cacheKey, $recommendations, $ttl > 0 ? $ttl : 60);

        return $recommendations;
    }

    /**
     * @return array<string, true> lowercase artist names with active skips
     */
    private function buildPenalizedArtists(int $userId): array
    {
        return TrackInteraction::query()
            ->where('track_interactions.user_id', $userId)
            ->where('track_interactions.type', 'skip')
            ->where('track_interactions.expires_at', '>', now())
            ->join('tracks', 'tracks.id', '=', 'track_interactions.track_id')
            ->join('artist_track', 'artist_track.track_id', '=', 'tracks.id')
            ->join('artists', 'artists.id', '=', 'artist_track.artist_model_id')
            ->pluck('artists.artist_name')
            ->map(fn (string $name) => mb_strtolower($name))
            ->flip()
            ->map(fn () => true)
            ->all();
    }

    /**
     * @param  Collection<int, UserRecentPlay>  $recentPlays
     * @return array<string, mixed>
     */
    private function buildExclusionSet(int $userId, Collection $recentPlays): array
    {
        $suppressed = TrackInteraction::query()
            /** @phpstan-ignore method.notFound */
            ->suppressedForUser($userId)
            ->join('tracks', 'tracks.id', '=', 'track_interactions.track_id')
            ->pluck('tracks.spotify_id')
            ->flip()
            ->all();

        $cutoff = now()->subDays(self::SUPPRESSION_WINDOW_DAYS);
        $recent = $recentPlays
            ->filter(fn (UserRecentPlay $p) => $p->played_at->gt($cutoff))
            ->map(fn (UserRecentPlay $p) => $p->track->spotify_id)
            ->filter()
            ->flip()
            ->all();

        $liked = DiscoveryLikedTrack::query()
            ->where('user_id', $userId)
            ->join('tracks', 'tracks.id', '=', 'discovery_liked_tracks.track_id')
            ->pluck('tracks.spotify_id')
            ->flip()
            ->all();

        return array_merge($suppressed, $recent, $liked);
    }

    /**
     * @param  array<string, array<string, mixed>>  $candidates
     * @return array<int, array<string, mixed>>
     */
    private function selectAndShape(array $candidates, int $userId): array
    {
        uasort($candidates, fn ($a, $b) => $b['match_score'] <=> $a['match_score']);
        $top = array_slice($candidates, 0, self::MAX_CANDIDATES, true);

        $keys = array_keys($top);
        $this->seededShuffle($keys, $userId + (int) now()->format('Ymd'));

        $count = min(max(count($keys), self::MIN_RECOMMENDATIONS), self::MAX_RECOMMENDATIONS);
        $keys = array_slice($keys, 0, $count);

        return array_values(array_map(fn (string $key) => [
            'track_id' => $top[$key]['track_id'],
            'match_score' => $top[$key]['match_score'],
        ], $keys));
    }

    /** @param string[] $keys */
    private function seededShuffle(array &$keys, int $seed): void
    {
        mt_srand($seed);
        for ($i = count($keys) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$keys[$i], $keys[$j]] = [$keys[$j], $keys[$i]];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $recommendations
     */
    private function persist(User $user, array $recommendations): void
    {
        $daily = DailyRecommendation::updateOrCreate(
            ['user_id' => $user->id, 'date' => now()->toDateString()],
            ['generated_at' => now()],
        );

        foreach ($recommendations as $position => $rec) {
            RecommendedTrack::updateOrCreate(
                ['daily_recommendation_id' => $daily->id, 'track_id' => $rec['track_id']],
                [
                    'match_score' => $rec['match_score'],
                    'position' => $position + 1,
                ],
            );
        }
    }
}
