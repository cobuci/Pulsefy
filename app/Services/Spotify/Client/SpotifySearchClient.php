<?php

namespace App\Services\Spotify\Client;

use App\Services\Spotify\Support\GlobalSpotifyRateLimit;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SpotifySearchClient
{
    private const string BASE_URL = 'https://api.spotify.com/v1';

    private const int MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly string $accessToken,
        private readonly ?GlobalSpotifyRateLimit $rateLimit = null,
    ) {}

    public function search(string $query, int $limit = 6, string $market = 'US'): Response
    {
        return $this->get('/search', [
            'q' => $query,
            'type' => 'artist,album,track',
            'limit' => $limit,
            'market' => $market,
        ]);
    }

    /**
     * Search for a specific track by name and artist.
     *
     * @return array{spotify_id: string, name: string, artist: string, album: string, image_url: string|null, preview_url: string|null}|null
     */
    public function searchTrack(string $trackName, string $artistName): ?array
    {
        $query = sprintf('track:"%s" artist:"%s"', $trackName, $artistName);

        $response = $this->get('/search', [
            'q' => $query,
            'type' => 'track',
            'limit' => 1,
            'market' => 'US',
        ]);

        if (! $response->successful()) {
            return null;
        }

        $item = $response->json('tracks.items.0');

        if (! is_array($item) || empty($item['id'])) {
            return null;
        }

        $images = $item['album']['images'] ?? [];
        $imageUrl = is_array($images) && isset($images[0]['url']) ? (string) $images[0]['url'] : null;

        $firstArtist = $item['artists'][0]['name'] ?? $artistName;

        return [
            'spotify_id' => (string) $item['id'],
            'name' => (string) $item['name'],
            'artist' => (string) $firstArtist,
            'album' => (string) ($item['album']['name'] ?? ''),
            'image_url' => $imageUrl,
            'preview_url' => isset($item['preview_url']) ? (string) $item['preview_url'] : null,
        ];
    }

    private function get(string $path, array $query = []): Response
    {
        return $this->request('GET', $path, ['query' => $query]);
    }

    private function request(string $method, string $path, array $options = []): Response
    {
        $rateLimit = $this->rateLimit ?? app(GlobalSpotifyRateLimit::class);
        $lastResponse = Http::response(status: 429);

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $rateLimit->throttle();

            $response = Http::withToken($this->accessToken)
                ->timeout(10)
                ->connectTimeout(5)
                ->retry(2, 500, fn ($e) => ! ($e instanceof RequestException && $e->response->status() === 429), throw: false)
                ->send($method, self::BASE_URL.$path, $options);

            $lastResponse = $response;

            if ($response->status() !== 429) {
                return $response;
            }

            if ($attempt < self::MAX_ATTEMPTS) {
                usleep($this->retryAfterMilliseconds($response, $attempt) * 1000);
            }
        }

        return $lastResponse;
    }

    private function retryAfterMilliseconds(Response $response, int $attempt): int
    {
        $retryAfter = $response->header('Retry-After');

        if (is_string($retryAfter) && is_numeric($retryAfter)) {
            return max(0, min(5000, ((int) $retryAfter) * 1000));
        }

        return min(5000, 200 * (2 ** ($attempt - 1)));
    }
}
