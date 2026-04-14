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

test('topTracks returns empty array when spotify responds with 401', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/top/tracks*' => Http::response(
            ['error' => ['status' => 401, 'message' => 'Permissions missing']],
            401,
        ),
    ]);

    $service = new SpotifyDataService($tokenService);

    expect($service->topTracks($user))->toBe([]);
});

test('topTracks returns empty array when spotify responds with 403', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/top/tracks*' => Http::response(
            ['error' => ['status' => 403, 'message' => 'Forbidden']],
            403,
        ),
    ]);

    $service = new SpotifyDataService($tokenService);

    expect($service->topTracks($user))->toBe([]);
});

test('topTracks returns empty array gracefully when api is unreachable', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/top/tracks*' => Http::failedConnection(),
    ]);

    $service = new SpotifyDataService($tokenService);

    expect($service->topTracks($user))->toBe([]);
});

test('topArtists returns empty array when spotify responds with 401', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/top/artists*' => Http::response(
            ['error' => ['status' => 401, 'message' => 'Permissions missing']],
            401,
        ),
    ]);

    $service = new SpotifyDataService($tokenService);

    expect($service->topArtists($user))->toBe([]);
});

test('recentlyPlayed returns empty array when spotify responds with 401', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player/recently-played*' => Http::response(
            ['error' => ['status' => 401, 'message' => 'Permissions missing']],
            401,
        ),
    ]);

    $service = new SpotifyDataService($tokenService);

    expect($service->recentlyPlayed($user))->toBe([]);
});

// ── currentlyPlaying ──────────────────────────────────────────────────────────

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

    $service = new SpotifyDataService($tokenService);
    $result = $service->currentlyPlaying($user);

    expect($result)->toBe([
        'is_playing' => true,
        'shuffle_state' => true,
        'progress_ms' => 30000,
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

    $service = new SpotifyDataService($tokenService);

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

    $service = new SpotifyDataService($tokenService);

    expect($service->currentlyPlaying($user))->toBeNull();
});

test('currentlyPlaying returns null when user lacks premium (403)', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player*' => Http::response(
            ['error' => ['status' => 403, 'message' => 'Premium required']],
            403,
        ),
    ]);

    $service = new SpotifyDataService($tokenService);

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

    $service = new SpotifyDataService($tokenService);

    expect($service->currentlyPlaying($user))->toBeNull();
});

test('currentlyPlaying returns null gracefully when api throws', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player*' => Http::failedConnection(),
    ]);

    $service = new SpotifyDataService($tokenService);

    expect($service->currentlyPlaying($user))->toBeNull();
});

test('currentlyPlaying returns track data when playback is paused', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player*' => Http::response([
            'is_playing' => false,
            'shuffle_state' => false,
            'progress_ms' => 45000,
            'currently_playing_type' => 'track',
            'item' => ['id' => 'track-paused', 'name' => 'Paused Song', 'duration_ms' => 200000],
        ]),
    ]);

    $service = new SpotifyDataService($tokenService);
    $result = $service->currentlyPlaying($user);

    expect($result)->toBe([
        'is_playing' => false,
        'shuffle_state' => false,
        'progress_ms' => 45000,
        'track' => ['id' => 'track-paused', 'name' => 'Paused Song', 'duration_ms' => 200000],
    ]);
});

// ── command ───────────────────────────────────────────────────────────────────

test('command returns true when spotify responds with 204', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player/play*' => Http::response(null, 204),
    ]);

    $service = new SpotifyDataService($tokenService);
    $result = $service->command($user, fn ($client) => $client->play());

    expect($result)->toBeTrue();
});

test('command returns false when spotify responds with 403 (Premium required)', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player/play*' => Http::response(
            ['error' => ['status' => 403, 'message' => 'Premium required']],
            403,
        ),
    ]);

    $service = new SpotifyDataService($tokenService);
    $result = $service->command($user, fn ($client) => $client->play());

    expect($result)->toBeFalse();
});

test('command returns false when spotify responds with 401 (missing scope)', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player/pause*' => Http::response(
            ['error' => ['status' => 401, 'message' => 'No token provided']],
            401,
        ),
    ]);

    $service = new SpotifyDataService($tokenService);
    $result = $service->command($user, fn ($client) => $client->pause());

    expect($result)->toBeFalse();
});

test('command returns false gracefully when network request fails', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player/next*' => Http::failedConnection(),
    ]);

    $service = new SpotifyDataService($tokenService);
    $result = $service->command($user, fn ($client) => $client->next());

    expect($result)->toBeFalse();
});
