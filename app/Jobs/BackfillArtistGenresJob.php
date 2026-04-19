<?php

namespace App\Jobs;

use App\Models\Artist;
use App\Services\LastFm\LastFmGenreService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

final class BackfillArtistGenresJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    private const int CHECK_COOLDOWN_HOURS = 24;

    private const int BATCH_SIZE = 30;

    private const int MAX_ARTISTS_PER_RUN = 120;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 900;

    public function uniqueId(): string
    {
        return 'artist-genres-backfill';
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('spotify-sync:lastfm-genre-backfill'))
                ->shared()
                ->releaseAfter(30)
                ->expireAfter(900),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 180];
    }

    public function handle(LastFmGenreService $lastFmGenreService): void
    {
        $processed = 0;

        while ($processed < self::MAX_ARTISTS_PER_RUN) {
            $remaining = self::MAX_ARTISTS_PER_RUN - $processed;
            $batchSize = min(self::BATCH_SIZE, $remaining);

            $artists = $this->candidatesQuery()
                ->orderBy('lastfm_genres_checked_at')
                ->orderBy('id')
                ->limit($batchSize)
                ->get();

            if ($artists->isEmpty()) {
                break;
            }

            foreach ($artists as $artist) {
                $genres = $lastFmGenreService->genresForArtistName((string) $artist->artist_name);

                $artist->lastfm_genres_checked_at = now();

                if ($genres !== []) {
                    $artist->genres = $genres;
                    $artist->fetched_at = now();
                    $artist->expires_at = now()->addDays(7);
                }

                $artist->save();
                $processed++;
            }
        }
    }

    private function candidatesQuery(): Builder
    {
        return Artist::query()
            ->whereNotNull('artist_name')
            ->where('artist_name', '!=', '')
            ->where(function ($query): void {
                $query
                    ->whereNull('genres')
                    ->orWhereJsonLength('genres', 0);
            })
            ->where(function ($query): void {
                $query
                    ->whereNull('lastfm_genres_checked_at')
                    ->orWhere('lastfm_genres_checked_at', '<=', now()->subHours(self::CHECK_COOLDOWN_HOURS));
            });
    }
}
