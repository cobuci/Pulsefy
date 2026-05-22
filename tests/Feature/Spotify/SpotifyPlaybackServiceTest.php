<?php

use App\Models\Playlist;
use App\Models\PlaylistTrack;
use App\Models\User;
use App\Services\Spotify\Playback\SpotifyPlaybackService;
use App\Services\Spotify\SpotifyTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

test('currentlyPlaying returns track data when a track is playing', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player*' => Http::response([
            'is_playing' => true,
            'shuffle_state' => true,
            'repeat_state' => 'context',
            'progress_ms' => 30000,
            'currently_playing_type' => 'track',
            'item' => ['id' => 'track1', 'name' => 'Test Song', 'duration_ms' => 180000],
        ]),
        'api.spotify.com/v1/me/library/contains*' => Http::response([false]),
    ]);

    $service = new SpotifyPlaybackService($tokenService);
    $result = $service->currentlyPlaying($user);

    expect($result)->toBe([
        'is_playing' => true,
        'shuffle_state' => true,
        'repeat_state' => 'context',
        'progress_ms' => 30000,
        'volume_percent' => null,
        'device_id' => null,
        'device_name' => null,
        'track' => ['id' => 'track1', 'name' => 'Test Song', 'duration_ms' => 180000],
        'is_saved' => false,
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

test('playMany returns true when spotify accepts context queue play', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player/play*' => Http::response(null, 204),
    ]);

    $service = new SpotifyPlaybackService($tokenService);

    expect($service->playMany($user, ['spotify:track:1', 'spotify:track:2'], 1))->toBeTrue();
});

test('setShuffle returns true when spotify responds with 204', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player/shuffle*' => Http::response(null, 204),
    ]);

    $service = new SpotifyPlaybackService($tokenService);

    expect($service->setShuffle($user, true))->toBeTrue();
});

test('setRepeat returns false for invalid mode', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->never();

    $service = new SpotifyPlaybackService($tokenService);

    expect($service->setRepeat($user, 'invalid-mode'))->toBeFalse();
});

test('setRepeat returns true when spotify responds with 204', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player/repeat*' => Http::response(null, 204),
    ]);

    $service = new SpotifyPlaybackService($tokenService);

    expect($service->setRepeat($user, 'track'))->toBeTrue();
});

test('isTrackSaved falls back to API when no liked playlist exists in DB', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/library/contains*' => Http::response([true]),
    ]);

    $service = new SpotifyPlaybackService($tokenService);

    expect($service->isTrackSaved($user, 'track456'))->toBeTrue();
});

test('isTrackSaved returns true from DB without hitting API when track is in liked playlist', function () {
    $user = User::factory()->create();

    $playlist = Playlist::factory()->create([
        'user_id' => $user->id,
        'is_liked_playlist' => true,
        'spotify_id' => 'liked-songs',
    ]);

    PlaylistTrack::factory()->create([
        'playlist_id' => $playlist->id,
        'spotify_track_id' => 'track123',
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->never();

    Http::fake();

    $service = new SpotifyPlaybackService($tokenService);

    expect($service->isTrackSaved($user, 'track123'))->toBeTrue();

    Http::assertNothingSent();
});

test('isTrackSaved calls API and inserts into DB when liked playlist exists but track is not in it and API returns true', function () {
    $user = User::factory()->create();

    $playlist = Playlist::factory()->create([
        'user_id' => $user->id,
        'is_liked_playlist' => true,
        'spotify_id' => 'liked-songs',
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/library/contains*' => Http::response([true]),
    ]);

    $service = new SpotifyPlaybackService($tokenService);

    expect($service->isTrackSaved($user, 'track456'))->toBeTrue();

    expect(
        PlaylistTrack::query()
            ->where('playlist_id', $playlist->id)
            ->where('spotify_track_id', 'track456')
            ->exists()
    )->toBeTrue();
});

test('isTrackSaved returns false and does not insert when liked playlist exists but API returns false', function () {
    $user = User::factory()->create();

    $playlist = Playlist::factory()->create([
        'user_id' => $user->id,
        'is_liked_playlist' => true,
        'spotify_id' => 'liked-songs',
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/library/contains*' => Http::response([false]),
    ]);

    $service = new SpotifyPlaybackService($tokenService);

    expect($service->isTrackSaved($user, 'track789'))->toBeFalse();

    expect(
        PlaylistTrack::query()
            ->where('playlist_id', $playlist->id)
            ->where('spotify_track_id', 'track789')
            ->exists()
    )->toBeFalse();
});

test('saveTrack inserts track into liked playlist in DB after successful API call', function () {
    $user = User::factory()->create();

    $playlist = Playlist::factory()->create([
        'user_id' => $user->id,
        'is_liked_playlist' => true,
        'spotify_id' => 'liked-songs',
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/library*' => Http::response(null, 200),
    ]);

    $service = new SpotifyPlaybackService($tokenService);

    expect($service->saveTrack($user, 'track_new'))->toBeTrue();

    expect(
        PlaylistTrack::query()
            ->where('playlist_id', $playlist->id)
            ->where('spotify_track_id', 'track_new')
            ->exists()
    )->toBeTrue();
});

test('unsaveTrack removes track from liked playlist in DB after successful API call', function () {
    $user = User::factory()->create();

    $playlist = Playlist::factory()->create([
        'user_id' => $user->id,
        'is_liked_playlist' => true,
        'spotify_id' => 'liked-songs',
    ]);

    PlaylistTrack::factory()->create([
        'playlist_id' => $playlist->id,
        'spotify_track_id' => 'track_del',
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/library*' => Http::response(null, 200),
    ]);

    $service = new SpotifyPlaybackService($tokenService);

    expect($service->unsaveTrack($user, 'track_del'))->toBeTrue();

    expect(
        PlaylistTrack::query()
            ->where('playlist_id', $playlist->id)
            ->where('spotify_track_id', 'track_del')
            ->exists()
    )->toBeFalse();
});

test('nextQueuedTrack returns first queue track', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player/queue' => Http::response([
            'queue' => [
                [
                    'id' => 'queued-1',
                    'name' => 'Queued Track',
                    'artists' => [['name' => 'Queued Artist']],
                    'album' => ['images' => []],
                    'duration_ms' => 180000,
                    'external_urls' => ['spotify' => 'https://open.spotify.com/track/queued-1'],
                ],
            ],
        ]),
    ]);

    $service = new SpotifyPlaybackService($tokenService);
    $result = $service->nextQueuedTrack($user);

    expect($result)->toMatchArray([
        'id' => 'queued-1',
        'name' => 'Queued Track',
        'duration_ms' => 180000,
    ]);
});

test('nextQueuedTrack returns null when queue is empty', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player/queue' => Http::response([
            'queue' => [],
        ]),
    ]);

    $service = new SpotifyPlaybackService($tokenService);

    expect($service->nextQueuedTrack($user))->toBeNull();
});

test('nextQueuedTrack returns null when spotify responds with 204', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player/queue' => Http::response(null, 204),
    ]);

    $service = new SpotifyPlaybackService($tokenService);

    expect($service->nextQueuedTrack($user))->toBeNull();
});
