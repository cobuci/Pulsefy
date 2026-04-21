<?php

namespace App\Services\Spotify\Insights;

use App\Models\User;
use App\Services\Spotify\Concerns\CachesStats;
use App\Services\Spotify\Contracts\SpotifyArtistProvider;
use App\Services\Spotify\Contracts\SpotifyInsightsProvider;
use App\Services\Spotify\Contracts\SpotifyStatsProvider;
use Illuminate\Support\Carbon;

/**
 * @phpstan-type InsightSeriesPoint array{label: string, value: int}
 * @phpstan-type InsightItem array{label: string, value: int, color: string}
 */
final readonly class SpotifyInsightsService implements SpotifyInsightsProvider
{
    use CachesStats;

    /**
     * @var array<int, string>
     */
    private const array GENRE_COLORS = [
        'oklch(0.92 0.1 200)',
        'oklch(0.72 0.11 188)',
        'oklch(0.65 0.13 155)',
        'oklch(0.78 0.08 220)',
        'oklch(0.55 0.1 188)',
    ];

    public function __construct(
        private SpotifyStatsProvider $stats,
        private SpotifyArtistProvider $artists,
    ) {}

    public function dashboard(User $user, string $timeRange = 'medium_term'): array
    {
        return $this->cached($user, 'insights_dashboard', 'v2:'.$timeRange, function () use ($user, $timeRange): array {
            $topTracks = $this->stats->topTracks($user, $timeRange);
            $topArtists = $this->stats->topArtists($user, $timeRange);
            $recentPlays = $this->stats->recentlyPlayed($user);
            $snapshot = $this->stats->topItemsSnapshot($user);
            $genreStats = $this->genreStats(
                $this->artistsFromSnapshot($snapshot, $timeRange, $topArtists),
            );

            return [
                'headline' => $this->dashboardHeadline($timeRange, $topArtists),
                'listeningTimeLabel' => $this->durationLabel((int) collect($topTracks)->sum(
                    fn (array $track): int => (int) data_get($track, 'duration_ms', 0),
                )),
                'uniqueTracksCount' => collect($topTracks)
                    ->pluck('id')
                    ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
                    ->unique()
                    ->count(),
                'activitySeries' => $this->activitySeries($recentPlays),
                'topGenre' => $genreStats['topGenre'],
                'topGenres' => $genreStats['items'],
                'genreMix' => $genreStats['items'],
                'recommendations' => $this->recommendations($topTracks, $recentPlays),
                'listeningHeatmap' => $this->listeningHeatmap($recentPlays),
            ];
        });
    }

    private function artistsFromSnapshot(array $snapshot, string $timeRange, array $fallback): array
    {
        $artists = data_get($snapshot, $timeRange.'.artists', []);

        if (! is_array($artists) || $artists === []) {
            return $fallback;
        }

        return $artists;
    }

    public function artist(User $user, string $artistId): array
    {
        return $this->cached($user, 'insights_artist', 'v1:'.$artistId, function () use ($user, $artistId): array {
            $topArtists = $this->stats->topArtists($user);
            $recentPlays = $this->stats->recentlyPlayed($user);
            $topTracks = $this->artists->topTracks($user, $artistId);

            $hours = $this->durationLabel((int) collect($topTracks)->sum(
                fn (array $track): int => (int) data_get($track, 'duration_ms', 0),
            ));

            $artist = $this->artists->artist($user, $artistId);
            $genre = is_string(data_get($artist, 'genres.0'))
                ? (string) data_get($artist, 'genres.0')
                : null;

            return [
                'rankLabel' => $this->artistRankLabel($topArtists, $artistId),
                'firstListenLabel' => $this->firstListenLabelForArtist($recentPlays, $artistId),
                'hoursLabel' => $hours,
                'genreLabel' => $genre,
            ];
        });
    }

    public function album(User $user, string $albumId): array
    {
        return $this->cached($user, 'insights_album', 'v1:'.$albumId, function () use ($user, $albumId): array {
            $recentPlays = $this->stats->recentlyPlayed($user);
            $albumTracks = $this->artists->albumTracks($user, $albumId);

            $albumTrackIds = collect($albumTracks)
                ->pluck('id')
                ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
                ->values();

            $plays = collect($recentPlays)
                ->filter(function (array $play) use ($albumTrackIds): bool {
                    $trackId = data_get($play, 'track.id');

                    return is_string($trackId) && $albumTrackIds->contains($trackId);
                })
                ->values();

            $totalDurationMs = $plays->sum(fn (array $play): int => (int) data_get($play, 'track.duration_ms', 0));
            $playsCount = $plays->count();
            $totalRecent = max(1, count($recentPlays));
            $affinity = (int) round(($playsCount / $totalRecent) * 100);

            return [
                'playsLabel' => (string) $playsCount,
                'timeLabel' => $this->durationLabel($totalDurationMs),
                'affinityLabel' => min(100, max(0, $affinity)).'%',
            ];
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $topArtists
     */
    private function dashboardHeadline(string $timeRange, array $topArtists): string
    {
        $genre = data_get($topArtists, '0.genres.0');
        $period = match ($timeRange) {
            'short_term' => 'last 4 weeks',
            'medium_term' => 'last 6 months',
            default => 'all time',
        };

        if (is_string($genre) && $genre !== '') {
            return "Your {$period} leaned into {$genre}.";
        }

        return "Your {$period} was full of discovery.";
    }

    /**
     * @param  array<int, array<string, mixed>>  $recentPlays
     * @return array<int, InsightSeriesPoint>
     */
    private function activitySeries(array $recentPlays): array
    {
        $days = collect(range(0, 6))
            ->map(function (int $offset): array {
                $date = now()->copy()->subDays(6 - $offset)->startOfDay();

                return [
                    'key' => $date->toDateString(),
                    'label' => $date->format('D'),
                    'value' => 0,
                ];
            })
            ->keyBy('key');

        foreach ($recentPlays as $play) {
            $playedAt = data_get($play, 'played_at');

            if (! is_string($playedAt) || $playedAt === '') {
                continue;
            }

            $key = Carbon::parse($playedAt)->startOfDay()->toDateString();

            if (! $days->has($key)) {
                continue;
            }

            $entry = $days->get($key);
            $entry['value']++;
            $days->put($key, $entry);
        }

        return $days
            ->values()
            ->map(fn (array $row): array => ['label' => $row['label'], 'value' => $row['value']])
            ->all();
    }

    private function genreStats(array $topArtists): array
    {
        $weightedGenres = [];
        $maxRank = max(1, count($topArtists));

        foreach ($topArtists as $index => $artist) {
            $genres = collect((array) data_get($artist, 'genres', []))
                ->filter(fn (mixed $genre): bool => is_string($genre) && $genre !== '')
                ->unique()
                ->values();

            if ($genres->isEmpty()) {
                continue;
            }

            $weight = max(1, $maxRank - $index);
            $share = $weight / $genres->count();

            foreach ($genres as $genre) {
                $weightedGenres[$genre] = ($weightedGenres[$genre] ?? 0.0) + $share;
            }
        }

        if ($weightedGenres === []) {
            return [
                'topGenre' => 'Mixed',
                'items' => [],
            ];
        }

        arsort($weightedGenres);

        $topFive = array_slice($weightedGenres, 0, 5, true);
        $sum = max(1.0, array_sum($topFive));

        $items = [];
        $index = 0;

        foreach ($topFive as $genre => $count) {
            $items[] = [
                'label' => $genre,
                'value' => (int) round(($count / $sum) * 100),
                'color' => self::GENRE_COLORS[$index] ?? self::GENRE_COLORS[0],
            ];
            $index++;
        }

        return [
            'topGenre' => (string) array_key_first($topFive),
            'items' => $items,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $topTracks
     * @param  array<int, array<string, mixed>>  $recentPlays
     * @return array<int, array<string, mixed>>
     */
    private function recommendations(array $topTracks, array $recentPlays): array
    {
        if (count($topTracks) >= 6) {
            return array_values(array_slice($topTracks, 3, 3));
        }

        $recentTracks = collect($recentPlays)
            ->pluck('track')
            ->filter(fn (mixed $track): bool => is_array($track))
            ->unique(fn (array $track): string => (string) ($track['id'] ?? ''))
            ->values();

        return $recentTracks->take(3)->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $recentPlays
     * @return array<int, array{hour: int, value: int}>
     */
    private function listeningHeatmap(array $recentPlays): array
    {
        $buckets = array_fill(0, 24, 0);

        foreach ($recentPlays as $play) {
            $playedAt = data_get($play, 'played_at');

            if (! is_string($playedAt) || $playedAt === '') {
                continue;
            }

            $hour = Carbon::parse($playedAt)->hour;
            $buckets[$hour]++;
        }

        $max = max(1, ...$buckets);

        return collect(range(0, 23))
            ->map(fn (int $hour): array => [
                'hour' => $hour,
                'value' => (int) round(($buckets[$hour] / $max) * 100),
            ])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $topArtists
     */
    private function artistRankLabel(array $topArtists, string $artistId): string
    {
        foreach ($topArtists as $index => $artist) {
            if ((string) data_get($artist, 'id') === $artistId) {
                return '#'.($index + 1);
            }
        }

        return '#—';
    }

    /**
     * @param  array<int, array<string, mixed>>  $recentPlays
     */
    private function firstListenLabelForArtist(array $recentPlays, string $artistId): string
    {
        $matches = collect($recentPlays)
            ->filter(function (array $play) use ($artistId): bool {
                $artists = data_get($play, 'track.artists', []);

                foreach ((array) $artists as $artist) {
                    if ((string) data_get($artist, 'id') === $artistId) {
                        return true;
                    }
                }

                return false;
            })
            ->pluck('played_at')
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->sort()
            ->values();

        if ($matches->isEmpty()) {
            return 'Not enough history';
        }

        return Carbon::parse((string) $matches->first())->format('M Y');
    }

    private function durationLabel(int $milliseconds): string
    {
        $minutes = (int) round($milliseconds / 60000);

        if ($minutes >= 60) {
            $hours = round($minutes / 60, 1);

            return $hours.'h';
        }

        return $minutes.'m';
    }
}
