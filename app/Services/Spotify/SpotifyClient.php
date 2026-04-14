<?php

namespace App\Services\Spotify;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class SpotifyClient
{
    private const BASE_URL = 'https://api.spotify.com/v1';

    public function __construct(private readonly string $accessToken) {}

    public function profile(): Response
    {
        return Http::withToken($this->accessToken)
            ->timeout(10)
            ->get(self::BASE_URL.'/me');
    }
}
