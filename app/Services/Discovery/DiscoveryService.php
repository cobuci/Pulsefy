<?php

namespace App\Services\Discovery;

use App\Enums\DailyRecommendationStatus;
use App\Models\DailyRecommendation;
use App\Models\DiscoveryLikedTrack;
use App\Models\RecommendedTrack;
use App\Models\TrackInteraction;
use App\Models\User;
use App\Models\UserRecentPlay;
use App\Models\UserTopArtist;
use App\Models\UserTopTrack;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

final class DiscoveryService
{
    public const int MIN_PENDING_BEFORE_TOP_UP = 5;

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
        $daily = $this->findToday($user);

        $topArtists = UserTopArtist::query()->where('user_id', $user->id)->with('artist')->get();
        $topTracks = UserTopTrack::query()->where('user_id', $user->id)->with('track.artists')->get();
        $recentPlays = UserRecentPlay::query()->where('user_id', $user->id)->latest('played_at')->limit(200)->with('track.artists')->get();

        $exclusionSet = array_merge(
            $this->buildExclusionSet($user->id, $recentPlays),
            $this->queuedSpotifyIds($user->id),
        );
        $penalizedArtists = $this->buildPenalizedArtists($user->id);
        $affinityMap = $this->affinityBuilder->build($user, $topArtists, $recentPlays);
        $candidates = $this->candidateResolver->resolve($user, $affinityMap, $topTracks, $exclusionSet, $recentPlays);

        foreach ($candidates as $id => $candidate) {
            $candidates[$id]['match_score'] = $this->scorer->score($candidate, $penalizedArtists);
        }

        $recommendations = $this->selectAndShape($candidates, $user->id, $this->pendingCount($user));

        $daily ??= DailyRecommendation::query()->updateOrCreate(
            ['user_id' => $user->id, 'date' => $this->today()],
            ['generated_at' => now(), 'started_at' => now()],
        );

        if ($recommendations !== []) {
            $this->persist($user, $recommendations);
        }

        $daily->update([
            'status' => $recommendations !== [] || $this->pendingCount($user) > 0
                ? DailyRecommendationStatus::Ready
                : DailyRecommendationStatus::Empty,
            'generated_at' => now(),
            'error_message' => null,
        ]);

        return $recommendations;
    }

    public function beginGeneration(User $user): DailyRecommendation
    {
        $daily = $this->findToday($user);

        if ($daily !== null) {
            $daily->update([
                'status' => DailyRecommendationStatus::Processing,
                'generated_at' => now(),
                'started_at' => now(),
                'error_message' => null,
            ]);

            return $daily->refresh();
        }

        return DailyRecommendation::query()->create([
            'user_id' => $user->id,
            'date' => $this->today(),
            'status' => DailyRecommendationStatus::Processing,
            'generated_at' => now(),
            'started_at' => now(),
        ]);
    }

    public function isGenerating(User $user): bool
    {
        return $this->processingDaily($user) !== null;
    }

    public function shouldTopUpQueue(User $user): bool
    {
        if ($this->isGenerating($user)) {
            return false;
        }

        if ($this->pendingCount($user) >= self::MIN_PENDING_BEFORE_TOP_UP) {
            return false;
        }

        $latestDaily = $this->latestDaily($user);

        if ($latestDaily === null) {
            return true;
        }

        if ($latestDaily->status->isPending()) {
            return false;
        }

        return ! in_array($latestDaily->status, [DailyRecommendationStatus::Failed, DailyRecommendationStatus::Empty], true);
    }

    public function latestDaily(User $user): ?DailyRecommendation
    {
        return DailyRecommendation::query()
            ->where('user_id', $user->id)
            ->latest('date')
            ->first();
    }

    public function pendingCount(User $user): int
    {
        return $this->pendingRecommendations($user)->count();
    }

    /**
     * @return SupportCollection<int, RecommendedTrack>
     */
    public function pendingRecommendations(User $user): SupportCollection
    {
        $interactedTrackIds = TrackInteraction::query()
            ->where('user_id', $user->id)
            ->pluck('track_id');

        return RecommendedTrack::query()
            ->whereHas('recommendation', fn ($query) => $query->where('user_id', $user->id))
            ->when(
                $interactedTrackIds->isNotEmpty(),
                fn ($query) => $query->whereNotIn('track_id', $interactedTrackIds),
            )
            ->with(['track.album'])
            ->join('daily_recommendations', 'daily_recommendations.id', '=', 'recommended_tracks.daily_recommendation_id')
            ->orderBy('daily_recommendations.date')
            ->orderBy('recommended_tracks.position')
            ->select('recommended_tracks.*')
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pendingRecommendationsForInertia(User $user): array
    {
        return $this->pendingRecommendations($user)
            ->map(fn (RecommendedTrack $recommendedTrack) => [
                'spotify_id' => $recommendedTrack->track->spotify_id,
                'name' => $recommendedTrack->track->name,
                'artist' => $recommendedTrack->artist_name,
                /** @phpstan-ignore nullsafe.neverNull */
                'album' => $recommendedTrack->track->album?->name ?? '',
                'image_url' => $recommendedTrack->track->image_url,
                'match_score' => $recommendedTrack->match_score,
            ])
            ->values()
            ->all();
    }

    public function processingDaily(User $user): ?DailyRecommendation
    {
        return DailyRecommendation::query()
            ->where('user_id', $user->id)
            ->where('status', DailyRecommendationStatus::Processing)
            ->latest('started_at')
            ->first();
    }

    private function today(): string
    {
        return now()->toDateString();
    }

    private function findToday(User $user): ?DailyRecommendation
    {
        return DailyRecommendation::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $this->today())
            ->first();
    }

    /**
     * @return array<string, true>
     */
    private function queuedSpotifyIds(int $userId): array
    {
        $ids = RecommendedTrack::query()
            ->whereHas('recommendation', fn ($query) => $query->where('user_id', $userId))
            ->join('tracks', 'tracks.id', '=', 'recommended_tracks.track_id')
            ->pluck('tracks.spotify_id');

        return array_fill_keys($ids->all(), true);
    }

    /**
     * @return array<string, true> lowercase artist names with active skips
     */
    private function buildPenalizedArtists(int $userId): array
    {
        $names = TrackInteraction::query()
            ->where('track_interactions.user_id', $userId)
            ->where('track_interactions.type', 'skip')
            ->where('track_interactions.expires_at', '>', now())
            ->join('tracks', 'tracks.id', '=', 'track_interactions.track_id')
            ->join('artist_track', 'artist_track.track_id', '=', 'tracks.id')
            ->join('artists', 'artists.id', '=', 'artist_track.artist_model_id')
            ->pluck('artists.artist_name')
            ->map(fn (string $name) => mb_strtolower($name))
            ->unique()
            ->values()
            ->all();

        return array_fill_keys($names, true);
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
            ->filter(fn (UserRecentPlay $play) => $play->played_at->gt($cutoff))
            ->map(fn (UserRecentPlay $play) => $play->track->spotify_id)
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
    private function selectAndShape(array $candidates, int $userId, int $pendingCount): array
    {
        uasort($candidates, fn ($a, $b) => $b['match_score'] <=> $a['match_score']);
        $top = array_slice($candidates, 0, self::MAX_CANDIDATES, true);

        $keys = array_keys($top);
        $this->seededShuffle($keys, $userId + $pendingCount + (int) now()->format('Ymd'));

        $count = min(max(count($keys), self::MIN_RECOMMENDATIONS), self::MAX_RECOMMENDATIONS);
        $keys = array_slice($keys, 0, $count);

        return array_values(array_map(fn (string $key) => [
            'track_id' => $top[$key]['track_id'],
            'match_score' => $top[$key]['match_score'],
            'artist_name' => (string) ($top[$key]['display_artist'] ?? ''),
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
        $daily = DailyRecommendation::query()->updateOrCreate(
            ['user_id' => $user->id, 'date' => $this->today()],
            ['generated_at' => now()],
        );

        $existingTrackIds = RecommendedTrack::query()
            ->whereHas('recommendation', fn ($query) => $query->where('user_id', $user->id))
            ->pluck('track_id')
            ->all();

        $position = (int) (RecommendedTrack::query()
            ->whereHas('recommendation', fn ($query) => $query->where('user_id', $user->id))
            ->max('position') ?? 0);

        foreach ($recommendations as $recommendation) {
            if (in_array($recommendation['track_id'], $existingTrackIds, true)) {
                continue;
            }

            $position++;

            RecommendedTrack::query()->create([
                'daily_recommendation_id' => $daily->id,
                'track_id' => $recommendation['track_id'],
                'artist_name' => $recommendation['artist_name'] ?? '',
                'match_score' => $recommendation['match_score'],
                'position' => $position,
            ]);

            $existingTrackIds[] = $recommendation['track_id'];
        }
    }
}
