<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('guests cannot access the check favorite endpoint', function () {
    $this->getJson(route('player.favorite.check', ['track_id' => 'abc']))
        ->assertUnauthorized();
});

test('returns 422 when track_id is missing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('player.favorite.check'))
        ->assertUnprocessable();
});

test('returns saved true when track is in library', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/library/contains*' => Http::response([true], 200),
    ]);

    $this->actingAs($user)
        ->getJson(route('player.favorite.check', ['track_id' => 'track123']))
        ->assertOk()
        ->assertJson(['ok' => true, 'saved' => true]);
});

test('returns saved false when track is not in library', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/library/contains*' => Http::response([false], 200),
    ]);

    $this->actingAs($user)
        ->getJson(route('player.favorite.check', ['track_id' => 'track123']))
        ->assertOk()
        ->assertJson(['ok' => true, 'saved' => false]);
});
