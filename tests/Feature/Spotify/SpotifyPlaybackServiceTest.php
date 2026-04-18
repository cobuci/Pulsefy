<?php

use App\Models\User;
use App\Services\Spotify\Playback\SpotifyPlaybackService;
use App\Services\Spotify\SpotifyTokenService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('currentlyPlaying returns track data when a track is playing', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player*' => Http::response([
            'is_playing' => true,
            'shuffle_state' => true,
            'progress_ms' => 30000,
            'currently_playing_type' => 'track',
            'item' => ['id' => 'track1', 'name' => 'Test Song', 'duration_ms' => 180000],
        ]),
    ]);

    $service = new SpotifyPlaybackService($tokenService);
    $result = $service->currentlyPlaying($user);

    expect($result)->toBe([
        'is_playing' => true,
        'shuffle_state' => true,
        'progress_ms' => 30000,
        'volume_percent' => null,
        'track' => ['id' => 'track1', 'name' => 'Test Song', 'duration_ms' => 180000],
    ]);
});

test('currentlyPlaying returns null when there is no playback state (204)', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player*' => Http::response(null, 204),
    ]);

    $service = new SpotifyPlaybackService($tokenService);

    expect($service->currentlyPlaying($user))->toBeNull();
});

test('currentlyPlaying returns null when scope is missing (401)', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player*' => Http::response(
            ['error' => ['status' => 401, 'message' => 'No token']],
            401,
        ),
    ]);

    $service = new SpotifyPlaybackService($tokenService);

    expect($service->currentlyPlaying($user))->toBeNull();
});

test('currentlyPlaying returns null when currently playing type is not a track', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player*' => Http::response([
            'is_playing' => true,
            'currently_playing_type' => 'episode',
            'item' => ['id' => 'ep1'],
        ]),
    ]);

    $service = new SpotifyPlaybackService($tokenService);

    expect($service->currentlyPlaying($user))->toBeNull();
});

test('resumePlay returns true when spotify responds with 204', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player/play*' => Http::response(null, 204),
    ]);

    $service = new SpotifyPlaybackService($tokenService);

    expect($service->resumePlay($user))->toBeTrue();
});

test('pause returns true when spotify responds with 204', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player/pause*' => Http::response(null, 204),
    ]);

    $service = new SpotifyPlaybackService($tokenService);

    expect($service->pause($user))->toBeTrue();
});

test('next returns true when spotify responds with 204', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player/next*' => Http::response(null, 204),
    ]);

    $service = new SpotifyPlaybackService($tokenService);

    expect($service->next($user))->toBeTrue();
});

test('previous returns true when spotify responds with 204', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player/previous*' => Http::response(null, 204),
    ]);

    $service = new SpotifyPlaybackService($tokenService);

    expect($service->previous($user))->toBeTrue();
});

test('next returns false gracefully when network request fails', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player/next*' => Http::failedConnection(),
    ]);

    $service = new SpotifyPlaybackService($tokenService);

    expect($service->next($user))->toBeFalse();
});
