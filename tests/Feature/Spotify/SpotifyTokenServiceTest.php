<?php

use App\Models\User;
use App\Services\Spotify\SpotifyTokenService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('returns existing token when not expired', function () {
    $user = User::factory()->create([
        'spotify_token' => 'valid-token',
        'spotify_token_expires_at' => now()->addHour(),
    ]);

    $service = new SpotifyTokenService;
    $token = $service->ensureFreshToken($user);

    expect($token)->toBe('valid-token');
});

test('refreshes token when expiring within 5 minutes', function () {
    $user = User::factory()->create([
        'spotify_token' => 'old-token',
        'spotify_refresh_token' => 'refresh-token',
        'spotify_token_expires_at' => now()->addMinutes(4),
    ]);

    Http::fake([
        'accounts.spotify.com/api/token' => Http::response([
            'access_token' => 'new-token',
            'expires_in' => 3600,
            'refresh_token' => 'new-refresh-token',
        ]),
    ]);

    $service = new SpotifyTokenService;
    $token = $service->ensureFreshToken($user);

    expect($token)->toBe('new-token');

    $user->refresh();
    expect($user->spotify_token)->toBe('new-token')
        ->and($user->spotify_refresh_token)->toBe('new-refresh-token');
});

test('refreshes token when already expired', function () {
    $user = User::factory()->create([
        'spotify_token' => 'expired-token',
        'spotify_refresh_token' => 'refresh-token',
        'spotify_token_expires_at' => now()->subMinutes(10),
    ]);

    Http::fake([
        'accounts.spotify.com/api/token' => Http::response([
            'access_token' => 'refreshed-token',
            'expires_in' => 3600,
        ]),
    ]);

    $service = new SpotifyTokenService;
    $token = $service->ensureFreshToken($user);

    expect($token)->toBe('refreshed-token');
});

test('keeps existing refresh token when not returned by spotify', function () {
    $user = User::factory()->create([
        'spotify_token' => 'old-token',
        'spotify_refresh_token' => 'original-refresh',
        'spotify_token_expires_at' => now()->subMinute(),
    ]);

    Http::fake([
        'accounts.spotify.com/api/token' => Http::response([
            'access_token' => 'new-token',
            'expires_in' => 3600,
            // no refresh_token in response
        ]),
    ]);

    $service = new SpotifyTokenService;
    $service->ensureFreshToken($user);

    $user->refresh();
    expect($user->spotify_refresh_token)->toBe('original-refresh');
});
