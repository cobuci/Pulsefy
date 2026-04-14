<?php

use App\Models\User;
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
    'player.shuffle',
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

// ── Premium required (403) ────────────────────────────────────────────────────

test('play returns 403 without ok when user lacks premium', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/play*' => Http::response(
            ['error' => ['status' => 403, 'message' => 'Player command failed: Premium required']],
            403,
        ),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.play'))
        ->assertForbidden()
        ->assertJson(['ok' => false]);
});

test('pause returns 403 without ok when user lacks premium', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/pause*' => Http::response(
            ['error' => ['status' => 403, 'message' => 'Player command failed: Premium required']],
            403,
        ),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.pause'))
        ->assertForbidden()
        ->assertJson(['ok' => false]);
});

test('next returns 403 without ok when user lacks premium', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/next*' => Http::response(
            ['error' => ['status' => 403, 'message' => 'Player command failed: Premium required']],
            403,
        ),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.next'))
        ->assertForbidden()
        ->assertJson(['ok' => false]);
});

test('shuffle returns 403 without ok when user lacks premium', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/shuffle*' => Http::response(
            ['error' => ['status' => 403, 'message' => 'Player command failed: Premium required']],
            403,
        ),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.shuffle'), ['state' => false])
        ->assertForbidden()
        ->assertJson(['ok' => false]);
});

// ── Missing scopes (401) ──────────────────────────────────────────────────────

test('play returns 403 when spotify token lacks required scope (401)', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/play*' => Http::response(
            ['error' => ['status' => 401, 'message' => 'No token provided']],
            401,
        ),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.play'))
        ->assertForbidden()
        ->assertJson(['ok' => false]);
});

// ── Network errors ────────────────────────────────────────────────────────────

test('play returns 403 gracefully when spotify api is unreachable', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/play*' => Http::failedConnection(),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.play'))
        ->assertForbidden()
        ->assertJson(['ok' => false]);
});

// ── Shuffle state parameter ───────────────────────────────────────────────────

test('shuffle passes state=false correctly', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/shuffle*' => Http::response(null, 204),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.shuffle'), ['state' => false])
        ->assertOk()
        ->assertJson(['ok' => true]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'state=false'));
});

test('shuffle defaults state to true when not provided', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/shuffle*' => Http::response(null, 204),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.shuffle'))
        ->assertOk()
        ->assertJson(['ok' => true]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'state=true'));
});
