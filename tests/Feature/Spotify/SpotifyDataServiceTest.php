<?php

use App\Models\SpotifyStat;
use App\Models\User;
use App\Services\Spotify\SpotifyDataService;
use App\Services\Spotify\SpotifyTokenService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('returns cached data when stat exists and is not expired', function () {
    $user = User::factory()->create();

    SpotifyStat::factory()->forTopTracks()->create([
        'user_id' => $user->id,
        'payload' => [['id' => 'track1', 'name' => 'Cached Track']],
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldNotReceive('ensureFreshToken');

    $service = new SpotifyDataService($tokenService);
    $result = $service->topTracks($user, 'medium_term');

    expect($result)->toBe([['id' => 'track1', 'name' => 'Cached Track']]);
});

test('fetches from api when cache is expired', function () {
    $user = User::factory()->create();

    SpotifyStat::factory()->forTopTracks()->expired()->create([
        'user_id' => $user->id,
        'payload' => [['id' => 'old_track']],
    ]);

    $freshToken = 'fresh-access-token';

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn($freshToken);

    Http::fake([
        'api.spotify.com/v1/me/top/tracks*' => Http::response([
            'items' => [['id' => 'new_track', 'name' => 'New Track']],
        ]),
    ]);

    $service = new SpotifyDataService($tokenService);
    $result = $service->topTracks($user, 'medium_term');

    expect($result)->toBe([['id' => 'new_track', 'name' => 'New Track']]);

    $this->assertDatabaseHas('spotify_stats', [
        'user_id' => $user->id,
        'type' => 'top_tracks',
        'time_range' => 'medium_term',
    ]);
});

test('fetches from api when no cache exists', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('some-token');

    Http::fake([
        'api.spotify.com/v1/me/top/artists*' => Http::response([
            'items' => [['id' => 'artist1', 'name' => 'Artist One']],
        ]),
    ]);

    $service = new SpotifyDataService($tokenService);
    $result = $service->topArtists($user, 'short_term');

    expect($result)->toHaveCount(1)
        ->and($result[0]['name'])->toBe('Artist One');
});

test('recently_played has 15 minute ttl', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player/recently-played*' => Http::response([
            'items' => [],
        ]),
    ]);

    $service = new SpotifyDataService($tokenService);
    $service->recentlyPlayed($user);

    $stat = SpotifyStat::where('user_id', $user->id)
        ->where('type', 'recently_played')
        ->first();

    expect($stat)->not->toBeNull()
        ->and($stat->expires_at->isAfter(now()))->toBeTrue()
        ->and($stat->expires_at->isBefore(now()->addMinutes(16)))->toBeTrue();
});

test('long_term stats have 72 hour ttl', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/top/tracks*' => Http::response(['items' => []]),
    ]);

    $service = new SpotifyDataService($tokenService);
    $service->topTracks($user, 'long_term');

    $stat = SpotifyStat::where('user_id', $user->id)->where('time_range', 'long_term')->first();

    expect($stat->expires_at->isAfter(now()->addHours(71)))->toBeTrue();
});
