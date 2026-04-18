<?php

namespace App\Services\LastFm;

final readonly class LastFmGenreService
{
    private const int MAX_TAGS = 5;

    private const int MIN_TAG_COUNT = 20;

    public function __construct(private LastFmClient $client) {}

    /**
     * @return array<int, string>
     */
    public function genresForArtistName(string $artistName): array
    {
        $tags = $this->client->artistTopTags($artistName);

        if ($tags === []) {
            return [];
        }

        return collect($tags)
            ->filter(function (mixed $tag): bool {
                if (! is_array($tag)) {
                    return false;
                }

                $name = data_get($tag, 'name');
                $count = (int) data_get($tag, 'count', 0);

                return is_string($name) && $name !== '' && $count >= self::MIN_TAG_COUNT;
            })
            ->map(fn (array $tag): string => mb_strtolower((string) data_get($tag, 'name')))
            ->unique()
            ->take(self::MAX_TAGS)
            ->values()
            ->all();
    }
}
