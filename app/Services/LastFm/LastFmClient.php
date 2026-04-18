<?php

namespace App\Services\LastFm;

use Illuminate\Support\Facades\Http;

final class LastFmClient
{
    private const string BASE_URL = 'https://ws.audioscrobbler.com/2.0/';

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
