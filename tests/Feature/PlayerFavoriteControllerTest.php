<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('guests cannot access the favorite endpoint', function () {
    $this->postJson(route('player.favorite'), ['track_id' => 'abc', 'favorite' => true])
        ->assertUnauthorized();
});

test('returns 422 when track_id is missing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('player.favorite'), ['favorite' => true])
        ->assertUnprocessable();
});

test('saves a track successfully', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/library*' => Http::response(null, 200),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.favorite'), ['track_id' => 'track123', 'favorite' => true])
        ->assertOk()
        ->assertJson(['ok' => true, 'favorite' => true]);
});

test('unsaves a track successfully', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/library*' => Http::response(null, 200),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.favorite'), ['track_id' => 'track123', 'favorite' => false])
        ->assertOk()
        ->assertJson(['ok' => true, 'favorite' => false]);
});

test('returns 422 when track_id is an empty string', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('player.favorite'), ['track_id' => '', 'favorite' => true])
        ->assertUnprocessable();
});

test('returns ok false with reauth flag when spotify returns 403', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/library*' => Http::response(null, 403),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.favorite'), ['track_id' => 'track123', 'favorite' => true])
        ->assertOk()
        ->assertJson(['ok' => false, 'requires_reauth' => true, 'favorite' => false]);
});
