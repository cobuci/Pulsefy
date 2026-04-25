<?php

namespace App\Services\LastFm;

use Illuminate\Support\Facades\Http;

final class LastFmClient
{
    private const string BASE_URL = 'https://ws.audioscrobbler.com/2.0/';

    /**
     * Fetch artists similar to the given artist.
     *
     * @return array<int, array{name: string, mbid: string, match: string}>
     */
    public function artistSimilar(string $artistName, int $limit = 20): array
    {
        $apiKey = (string) config('services.lastfm.api_key');

        if ($apiKey === '') {
            return [];
        }

        try {
            $response = Http::timeout(8)
                ->connectTimeout(5)
                ->retry(2, 300)
                ->get(self::BASE_URL, [
                    'method' => 'artist.getSimilar',
                    'artist' => $artistName,
                    'limit' => $limit,
                    'autocorrect' => 1,
                    'api_key' => $apiKey,
                    'format' => 'json',
                ]);

            if (! $response->successful()) {
                return [];
            }

            $artists = $response->json('similarartists.artist');

            return is_array($artists) ? $artists : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Fetch tracks similar to the given track.
     *
     * @return array<int, array{name: string, artist: array{name: string}, match: string}>
     */
    public function trackSimilar(string $artistName, string $trackName, int $limit = 20): array
    {
        $apiKey = (string) config('services.lastfm.api_key');

        if ($apiKey === '') {
            return [];
        }

        try {
            $response = Http::timeout(8)
                ->connectTimeout(5)
                ->retry(2, 300)
                ->get(self::BASE_URL, [
                    'method' => 'track.getSimilar',
                    'artist' => $artistName,
                    'track' => $trackName,
                    'limit' => $limit,
                    'autocorrect' => 1,
                    'api_key' => $apiKey,
                    'format' => 'json',
                ]);

            if (! $response->successful()) {
                return [];
            }

            $tracks = $response->json('similartracks.track');

            return is_array($tracks) ? $tracks : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function artistTopTags(string $artistName): array
    {
        $apiKey = (string) config('services.lastfm.api_key');

        if ($apiKey === '') {
            return [];
        }

        $response = Http::timeout(8)
            ->connectTimeout(5)
            ->retry(2, 300)
            ->get(self::BASE_URL, [
                'method' => 'artist.getTopTags',
                'artist' => $artistName,
                'api_key' => $apiKey,
                'format' => 'json',
                'autocorrect' => 1,
            ]);

        if (! $response->successful()) {
            return [];
        }

        return (array) $response->json('toptags.tag', []);
    }
}
