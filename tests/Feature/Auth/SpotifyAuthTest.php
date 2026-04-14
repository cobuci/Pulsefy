<?php

use App\Models\User;
use App\Services\Spotify\SpotifyAuthService;
use Illuminate\Support\Carbon;
use Laravel\Socialite\Contracts\User as SocialiteUser;

test('redirect returns a redirect to spotify', function () {
    $response = $this->get('/api/spotify/redirect');

    $response->assertRedirectContains('accounts.spotify.com');
});

test('callback creates a new user and authenticates', function () {
    $spotifyUser = Mockery::mock(SocialiteUser::class);
    $spotifyUser->token = 'access-token';
    $spotifyUser->refreshToken = 'refresh-token';
    $spotifyUser->expiresIn = 3600;

    $spotifyUser->shouldReceive('getId')->andReturn('spotify123');
    $spotifyUser->shouldReceive('getName')->andReturn('Jane Doe');
    $spotifyUser->shouldReceive('getEmail')->andReturn('jane@example.com');
    $spotifyUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');

    $service = new SpotifyAuthService;
    $user = $service->findOrCreateUser($spotifyUser);

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->spotify_id)->toBe('spotify123')
        ->and($user->name)->toBe('Jane Doe')
        ->and($user->email)->toBe('jane@example.com')
        ->and($user->avatar)->toBe('https://example.com/avatar.jpg')
        ->and($user->spotify_token)->toBe('access-token')
        ->and($user->spotify_refresh_token)->toBe('refresh-token');
});

test('callback updates existing user tokens', function () {
    $existing = User::factory()->create([
        'spotify_id' => 'spotify456',
        'name' => 'Old Name',
        'spotify_token' => 'old-token',
    ]);

    $spotifyUser = Mockery::mock(SocialiteUser::class);
    $spotifyUser->token = 'new-token';
    $spotifyUser->refreshToken = 'new-refresh';
    $spotifyUser->expiresIn = 3600;

    $spotifyUser->shouldReceive('getId')->andReturn('spotify456');
    $spotifyUser->shouldReceive('getName')->andReturn('New Name');
    $spotifyUser->shouldReceive('getEmail')->andReturn('new@example.com');
    $spotifyUser->shouldReceive('getAvatar')->andReturn('https://example.com/new-avatar.jpg');

    $service = new SpotifyAuthService;
    $user = $service->findOrCreateUser($spotifyUser);

    expect(User::count())->toBe(1)
        ->and($user->id)->toBe($existing->id)
        ->and($user->name)->toBe('New Name')
        ->and($user->spotify_token)->toBe('new-token');
});

test('callback expires_at is set correctly', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));

    $spotifyUser = Mockery::mock(SocialiteUser::class);
    $spotifyUser->token = 'token';
    $spotifyUser->refreshToken = 'refresh';
    $spotifyUser->expiresIn = 3600;

    $spotifyUser->shouldReceive('getId')->andReturn('spotify789');
    $spotifyUser->shouldReceive('getName')->andReturn('Test');
    $spotifyUser->shouldReceive('getEmail')->andReturn('test@example.com');
    $spotifyUser->shouldReceive('getAvatar')->andReturn(null);

    $service = new SpotifyAuthService;
    $user = $service->findOrCreateUser($spotifyUser);

    expect($user->spotify_token_expires_at->toDateTimeString())->toBe('2026-01-01 01:00:00');

    Carbon::setTestNow();
});
