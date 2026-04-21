<?php

namespace App\Services\Spotify\Artist;

use App\Models\Artist;
use App\Services\LastFm\LastFmGenreService;

final readonly class ArtistGenreCacheService
{
    private const int GENRE_CACHE_TTL_SECONDS = 7 * 24 * 3600;

    private const int MAX_FETCHED_ARTISTS_PER_RUN = 30;

    public function __construct(private LastFmGenreService $lastFmGenreService) {}

    /**
     * @param  array<int, array<string, mixed>>  $artists
     * @return array<int, array<string, mixed>>
     */
    public function mergeGenres(array $artists): array
    {
        if ($artists === []) {
            return [];
        }

        $artistsById = collect($artists)
            ->filter(fn (array $artist): bool => is_string(data_get($artist, 'id')) && data_get($artist, 'id') !== '')
            ->mapWithKeys(fn (array $artist): array => [(string) data_get($artist, 'id') => (string) data_get($artist, 'name', '')])
            ->filter(fn (string $name): bool => $name !== '')
            ->all();

        $artistIds = array_keys($artistsById);

        if ($artistIds === []) {
            return $artists;
        }

        $cachedGenres = $this->cachedGenresByArtistId($artistIds);
        $missingIds = collect($artistIds)
            ->filter(fn (string $id): bool => ! isset($cachedGenres[$id]))
            ->values()
            ->all();

        if ($missingIds !== []) {
            $fetchedGenres = $this->fetchAndPersistGenres($artistsById, $missingIds);
            $cachedGenres = [...$cachedGenres, ...$fetchedGenres];
        }

        return collect($artists)
            ->map(function (array $artist) use ($cachedGenres): array {
                $artistId = data_get($artist, 'id');

                if (! is_string($artistId) || $artistId === '') {
                    return $artist;
                }

                $genres = $cachedGenres[$artistId] ?? [];
                $artist['genres'] = $genres;

                return $artist;
            })
            ->all();
    }

    /**
     * @param  array<int, string>  $artistIds
     * @return array<string, array<int, string>>
     */
    private function cachedGenresByArtistId(array $artistIds): array
    {
        return Artist::query()
            ->whereIn('artist_id', $artistIds)
            ->where('expires_at', '>', now())
            ->get()
            ->mapWithKeys(function (Artist $record): array {
                $genres = is_array($record->genres) ? $record->genres : [];

                return [$record->artist_id => array_values(array_filter($genres, fn (string $genre): bool => $genre !== ''))];
            })
            ->all();
    }

    /**
     * @param  array<int, string>  $artistIds
     * @return array<string, array<int, string>>
     */
    private function fetchAndPersistGenres(array $artistsById, array $artistIds): array
    {
        $genresByArtistId = [];

        foreach (array_slice($artistIds, 0, self::MAX_FETCHED_ARTISTS_PER_RUN) as $artistId) {
            $artistName = $artistsById[$artistId] ?? '';

            if ($artistName === '') {
                continue;
            }

            $genres = $this->lastFmGenreService->genresForArtistName($artistName);

            if ($genres === []) {
                continue;
            }

            $genresByArtistId[$artistId] = $genres;

            Artist::query()->updateOrCreate(
                ['artist_id' => $artistId],
                [
                    'artist_name' => $artistName,
                    'genres' => $genres,
                    'fetched_at' => now(),
                    'expires_at' => now()->addSeconds(self::GENRE_CACHE_TTL_SECONDS),
                ],
            );
        }

        return $genresByArtistId;
    }
}
