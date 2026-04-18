<?php

namespace App\Services\Spotify\Client;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class SpotifyArtistClient
{
    private const string BASE_URL = 'https://api.spotify.com/v1';

    public function __construct(private readonly string $accessToken) {}

    public function artist(string $artistId): Response
    {
        return $this->get('/artists/'.$artistId);
    }

    public function artistAlbums(string $artistId, int $limit = 10, string $market = 'US'): Response
    {
        return $this->get('/artists/'.$artistId.'/albums', [
            'market' => $market,
            'include_groups' => 'album,single,appears_on,compilation',
            'limit' => $limit,
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

    private function get(string $path, array $query = []): Response
    {
        return Http::withToken($this->accessToken)
            ->timeout(10)
            ->connectTimeout(5)
            ->retry(2, 500, fn ($e) => ! ($e instanceof RequestException && $e->response?->status() === 429))
            ->get(self::BASE_URL.$path, $query);
    }
}
