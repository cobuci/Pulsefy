<?php

namespace App\Services\Discovery;

use App\Models\DiscoveryLikedTrack;
use App\Models\User;
use App\Models\UserRecentPlay;
use App\Models\UserTopArtist;
use Illuminate\Database\Eloquent\Collection;

final class DiscoveryAffinityBuilder
{
    private const array TIME_RANGE_WEIGHTS = [
        'short_term' => 3.0,
        'medium_term' => 2.0,
        'long_term' => 1.0,
    ];

    private const int RECENCY_SUPPLEMENT = 20;

    private const int LIKES_BOOST = 20;

    /**
     * @param  Collection<int, UserTopArtist>  $topArtists
     * @param  Collection<int, UserRecentPlay>  $recentPlays
     * @return array<string, float>
     */
    public function build(User $user, Collection $topArtists, Collection $recentPlays): array
    {
        $map = $this->fromTopArtists($topArtists);
        $map = $this->supplementWithRecency($map, $recentPlays);
        $map = $this->applyLikesBoost($map, $user);

        arsort($map);

        return $map;
    }

    /**
     * @param  Collection<int, UserTopArtist>  $topArtists
     * @return array<string, float>
     */
    private function fromTopArtists(Collection $topArtists): array
    {
        $raw = [];

        foreach ($topArtists as $topArtist) {
            $name = $topArtist->artist->artist_name ?? '';
            if ($name === '') {
                continue;
            }

            $weight = self::TIME_RANGE_WEIGHTS[$topArtist->time_range] ?? 1.0;
            $raw[$name] = ($raw[$name] ?? 0.0) + ($topArtist->score * $weight) / sqrt(max(1, $topArtist->rank));
        }

        return $this->normalize($raw);
    }

    /**
     * @param  array<string, float>  $map
     * @param  Collection<int, UserRecentPlay>  $recentPlays
     * @return array<string, float>
     */
    private function supplementWithRecency(array $map, Collection $recentPlays): array
    {
        $recentPlays
            ->flatMap(fn (UserRecentPlay $p) => $p->track->artists->map(fn ($a) => $a->artist_name)->filter())
            ->unique()
            ->each(function (string $name) use (&$map): void {
                if (! isset($map[$name])) {
                    $map[$name] = self::RECENCY_SUPPLEMENT;
                }
            });

        return $map;
    }

    /**
     * @param  array<string, float>  $map
     * @return array<string, float>
     */
    private function applyLikesBoost(array $map, User $user): array
    {
        DiscoveryLikedTrack::query()
            ->where('discovery_liked_tracks.user_id', $user->id)
            ->join('tracks', 'tracks.id', '=', 'discovery_liked_tracks.track_id')
            ->join('artist_track', 'artist_track.track_id', '=', 'tracks.id')
            ->join('artists', 'artists.id', '=', 'artist_track.artist_model_id')
            ->pluck('artists.artist_name')
            ->filter()
            ->unique()
            ->each(function (string $name) use (&$map): void {
                $map[$name] = min(100.0, ($map[$name] ?? 0.0) + self::LIKES_BOOST);
            });

        return $map;
    }

    /**
     * @param  array<string, float>  $raw
     * @return array<string, float>
     */
    private function normalize(array $raw): array
    {
        if ($raw === []) {
            return [];
        }

        $max = max($raw);

        if ($max <= 0) {
            return $raw;
        }

        return array_map(fn (float $v): float => ($v / $max) * 100, $raw);
    }
}
