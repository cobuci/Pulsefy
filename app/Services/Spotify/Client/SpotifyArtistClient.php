<?php

namespace App\Services\Spotify\Client;

use App\Services\Spotify\Support\GlobalSpotifyRateLimit;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class SpotifyArtistClient
{
    private const string BASE_URL = 'https://api.spotify.com/v1';

    private const int MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly string $accessToken,
        private readonly ?GlobalSpotifyRateLimit $rateLimit = null,
    ) {}

    public function artist(string $artistId): Response
    {
        return $this->get('/artists/'.$artistId);
    }

    public function artistAlbums(string $artistId, int $limit = 10, int $offset = 0, string $market = 'US'): Response
    {
        return $this->get('/artists/'.$artistId.'/albums', [
            'market' => $market,
            'include_groups' => 'album,single,appears_on,compilation',
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    public function album(string $albumId, string $market = 'US'): Response
    {
        return $this->get('/albums/'.$albumId, [
            'market' => $market,
        ]);
    }

    public function albumTracks(string $albumId, int $limit = 50, int $offset = 0, string $market = 'US'): Response
    {
        return $this->get('/albums/'.$albumId.'/tracks', [
            'market' => $market,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    public function searchTracksByArtist(string $artistName, int $limit = 10, string $market = 'US'): Response
    {
        return $this->get('/search', [
            'q' => 'artist:"'.$artistName.'"',
            'type' => 'track',
            'limit' => $limit,
            'market' => $market,
        ]);
    }

    public function artistTopTracks(string $artistId, string $market = 'US'): Response
    {
        return $this->get('/artists/'.$artistId.'/top-tracks', [
            'market' => $market,
        ]);
    }

    public function libraryContains(array $uris): Response
    {
        return $this->get('/me/library/contains', [
            'uris' => implode(',', $uris),
        ]);
    }

    public function saveToLibrary(array $uris): Response
    {
        return $this->put('/me/library', [
            'uris' => implode(',', $uris),
        ]);
    }

    public function removeFromLibrary(array $uris): Response
    {
        return $this->delete('/me/library', [
            'uris' => implode(',', $uris),
        ]);
    }

    private function get(string $path, array $query = []): Response
    {
        return $this->request('GET', $path, ['query' => $query]);
    }

    private function put(string $path, array $query = []): Response
    {
        return $this->request('PUT', $path, ['query' => $query]);
    }

    private function delete(string $path, array $query = []): Response
    {
        return $this->request('DELETE', $path, ['query' => $query]);
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
                ->retry(2, 500, fn ($e) => ! ($e instanceof RequestException && $e->response?->status() === 429), throw: false)
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
