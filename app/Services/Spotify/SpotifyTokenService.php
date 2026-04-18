<?php

namespace App\Services\Spotify;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SpotifyTokenService
{
    private const string TOKEN_URL = 'https://accounts.spotify.com/api/token';

    private const string APP_TOKEN_CACHE_KEY = 'spotify_app_access_token';

    public function ensureFreshToken(User $user): string
    {
        if (! $user->isSpotifyTokenExpired()) {
            return $user->spotify_token;
        }

        return $this->refresh($user);
    }

    /** @throws RequestException|ConnectionException */
    public function appAccessToken(): string
    {
        return Cache::remember(self::APP_TOKEN_CACHE_KEY, now()->addMinutes(50), function (): string {
            $clientId = config('services.spotify.client_id');
            $clientSecret = config('services.spotify.client_secret');

            $response = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->timeout(10)
                ->retry(2, 500)
                ->post(self::TOKEN_URL, [
                    'grant_type' => 'client_credentials',
                ]);

            $response->throw();

            return (string) $response->json('access_token');
        });
    }

    /** @throws RequestException|ConnectionException */
    public function refresh(User $user): string
    {
        $clientId = config('services.spotify.client_id');
        $clientSecret = config('services.spotify.client_secret');

        $response = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->timeout(10)
            ->retry(2, 500)
            ->post(self::TOKEN_URL, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $user->spotify_refresh_token,
            ]);

        $response->throw();

        $data = $response->json();

        $user->update([
            'spotify_token' => $data['access_token'],
            'spotify_refresh_token' => $data['refresh_token'] ?? $user->spotify_refresh_token,
            'spotify_token_expires_at' => Carbon::now()->addSeconds($data['expires_in']),
        ]);

        return $data['access_token'];
    }
}
