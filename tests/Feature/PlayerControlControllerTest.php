<?php

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

// ── Authentication ────────────────────────────────────────────────────────────

test('guests cannot access player control endpoints', function (string $route) {
    $this->postJson(route($route))
        ->assertUnauthorized();
})->with([
    'player.play',
    'player.pause',
    'player.next',
    'player.previous',
    'player.volume',
    'player.shuffle',
    'player.repeat',
]);

// ── Successful commands ───────────────────────────────────────────────────────

test('play returns ok when spotify accepts the command (204)', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/play*' => Http::response(null, 204),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.play'))
        ->assertOk()
        ->assertJson(['ok' => true]);
});

test('play returns ok when spotify accepts the command with uri (202)', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/play*' => Http::response(null, 202),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.play'), ['uri' => 'spotify:track:4iV5W9uYEdYUVa79Axb7Rh'])
        ->assertOk()
        ->assertJson(['ok' => true]);
});

test('play returns ok when spotify accepts the command with uris queue and offset', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/play*' => Http::response(null, 204),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.play'), [
            'uris' => [
                'spotify:track:one',
                'spotify:track:two',
            ],
            'offset_position' => 1,
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);
});

test('pause returns ok when spotify accepts the command (204)', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/pause*' => Http::response(null, 204),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.pause'))
        ->assertOk()
        ->assertJson(['ok' => true]);
});

test('next returns ok when spotify accepts the command (204)', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/next*' => Http::response(null, 204),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.next'))
        ->assertOk()
        ->assertJson(['ok' => true]);
});

test('previous returns ok when spotify accepts the command (204)', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/previous*' => Http::response(null, 204),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.previous'))
        ->assertOk()
        ->assertJson(['ok' => true]);
});

test('shuffle returns ok when spotify accepts the command (204)', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/shuffle*' => Http::response(null, 204),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.shuffle'), ['state' => true])
        ->assertOk()
        ->assertJson(['ok' => true]);
});

test('repeat returns ok when spotify accepts the command (204)', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/repeat*' => Http::response(null, 204),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.repeat'), ['state' => 'context'])
        ->assertOk()
        ->assertJson(['ok' => true]);
});

test('volume returns ok when spotify accepts the command (204)', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/volume*' => Http::response(null, 204),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.volume'), ['volume_percent' => 70])
        ->assertOk()
        ->assertJson(['ok' => true]);
});

test('volume clamps out-of-range values before calling spotify', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/volume*' => Http::response(null, 204),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.volume'), ['volume_percent' => 999])
        ->assertOk()
        ->assertJson(['ok' => true]);

    Http::assertSent(function (Request $request): bool {
        return $request->method() === 'PUT'
            && str_contains($request->url(), '/v1/me/player/volume?volume_percent=100');
    });
});

test('repeat returns ok:false for invalid mode', function () {
    $user = User::factory()->create();

    Http::fake();

    $this->actingAs($user)
        ->postJson(route('player.repeat'), ['state' => 'invalid'])
        ->assertOk()
        ->assertJson(['ok' => false]);
});

// ── Premium required (403) ────────────────────────────────────────────────────

test('play returns ok:false without ok when user lacks premium', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/play*' => Http::response(
            ['error' => ['status' => 403, 'message' => 'Player command failed: Premium required']],
            403,
        ),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.play'))
        ->assertOk()
        ->assertJson(['ok' => false]);
});

test('pause returns ok:false without ok when user lacks premium', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/pause*' => Http::response(
            ['error' => ['status' => 403, 'message' => 'Player command failed: Premium required']],
            403,
        ),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.pause'))
        ->assertOk()
        ->assertJson(['ok' => false]);
});

test('next returns ok:false without ok when user lacks premium', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/next*' => Http::response(
            ['error' => ['status' => 403, 'message' => 'Player command failed: Premium required']],
            403,
        ),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.next'))
        ->assertOk()
        ->assertJson(['ok' => false]);
});

// ── Missing scopes (401) ──────────────────────────────────────────────────────

test('play returns ok:false when spotify token lacks required scope (401)', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/play*' => Http::response(
            ['error' => ['status' => 401, 'message' => 'No token provided']],
            401,
        ),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.play'))
        ->assertOk()
        ->assertJson(['ok' => false]);
});

// ── Network errors ────────────────────────────────────────────────────────────

test('play returns ok:false gracefully when spotify api is unreachable', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/play*' => Http::failedConnection(),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.play'))
        ->assertOk()
        ->assertJson(['ok' => false]);
});

test('play with uri retries with device_id when no active device (404)', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/play' => Http::response(
            ['error' => ['status' => 404, 'message' => 'Player command failed: No active device found', 'reason' => 'NO_ACTIVE_DEVICE']],
            404,
        ),
        'api.spotify.com/v1/me/player/devices' => Http::response(
            ['devices' => [['id' => 'device-abc', 'is_active' => false, 'is_restricted' => false, 'name' => 'Test Device', 'type' => 'Computer', 'volume_percent' => 80, 'supports_volume' => true]]],
            200,
        ),
        'api.spotify.com/v1/me/player/play?device_id=device-abc' => Http::response(null, 202),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.play'), ['uri' => 'spotify:track:4iV5W9uYEdYUVa79Axb7Rh'])
        ->assertOk()
        ->assertJson(['ok' => true]);
});

test('play with uri returns ok:false when no active device and no devices available', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/play*' => Http::response(
            ['error' => ['status' => 404, 'message' => 'Player command failed: No active device found', 'reason' => 'NO_ACTIVE_DEVICE']],
            404,
        ),
        'api.spotify.com/v1/me/player/devices' => Http::response(['devices' => []], 200),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.play'), ['uri' => 'spotify:track:4iV5W9uYEdYUVa79Axb7Rh'])
        ->assertOk()
        ->assertJson(['ok' => false]);
});
