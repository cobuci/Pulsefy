<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('guests cannot access now-playing endpoint', function () {
    $this->getJson(route('player.now-playing'))
        ->assertUnauthorized();
});

test('returns 204 when there is no playback state', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player*' => Http::response(null, 204),
    ]);

    $this->actingAs($user)
        ->getJson(route('player.now-playing'))
        ->assertNoContent();
});

test('returns 204 when scope is missing (401 from spotify)', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player*' => Http::response(
            ['error' => ['status' => 401, 'message' => 'No token']],
            401,
        ),
    ]);

    $this->actingAs($user)
        ->getJson(route('player.now-playing'))
        ->assertNoContent();
});

test('returns 204 when user lacks premium or playback permission (403 from spotify)', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player*' => Http::response(
            ['error' => ['status' => 403, 'message' => 'Player command failed: Premium required']],
            403,
        ),
    ]);

    $this->actingAs($user)
        ->getJson(route('player.now-playing'))
        ->assertNoContent();
});

test('returns 204 when currently playing type is not a track (e.g. podcast)', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player*' => Http::response([
            'is_playing' => true,
            'currently_playing_type' => 'episode',
            'item' => ['id' => 'ep1'],
        ]),
    ]);

    $this->actingAs($user)
        ->getJson(route('player.now-playing'))
        ->assertNoContent();
});

test('returns 204 when item is empty', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player*' => Http::response([
            'is_playing' => true,
            'currently_playing_type' => 'track',
            'item' => null,
        ]),
    ]);

    $this->actingAs($user)
        ->getJson(route('player.now-playing'))
        ->assertNoContent();
});

test('returns now-playing data when a track exists and playback is paused', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player*' => Http::response([
            'is_playing' => false,
            'shuffle_state' => false,
            'progress_ms' => 45000,
            'currently_playing_type' => 'track',
            'item' => ['id' => 'track1', 'name' => 'Song A', 'duration_ms' => 200000],
        ]),
        'api.spotify.com/v1/me/library/contains*' => Http::response([false], 200),
    ]);

    $this->actingAs($user)
        ->getJson(route('player.now-playing'))
        ->assertOk()
        ->assertJson([
            'is_playing' => false,
            'shuffle_state' => false,
            'progress_ms' => 45000,
            'track' => ['id' => 'track1', 'name' => 'Song A'],
            'is_saved' => false,
        ]);
});

test('returns now-playing data when a track is playing', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player*' => Http::response([
            'is_playing' => true,
            'shuffle_state' => false,
            'progress_ms' => 12000,
            'currently_playing_type' => 'track',
            'item' => ['id' => 'track2', 'name' => 'Song B', 'duration_ms' => 210000],
        ]),
        'api.spotify.com/v1/me/library/contains*' => Http::response([true], 200),
    ]);

    $this->actingAs($user)
        ->getJson(route('player.now-playing'))
        ->assertOk()
        ->assertJson([
            'is_playing' => true,
            'shuffle_state' => false,
            'progress_ms' => 12000,
            'track' => ['id' => 'track2', 'name' => 'Song B'],
            'is_saved' => true,
        ]);
});

test('returns 204 gracefully when spotify api is unreachable', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player*' => Http::failedConnection(),
    ]);

    $this->actingAs($user)
        ->getJson(route('player.now-playing'))
        ->assertNoContent();
});
