<?php

use App\Models\DiscoveryLikedTrack;
use App\Models\User;

test('guests cannot access liked playlist', function () {
    $this->getJson(route('discovery.liked-playlist'))
        ->assertUnauthorized();
});

test('returns empty list when no liked tracks', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('discovery.liked-playlist'))
        ->assertOk()
        ->assertJson(['data' => [], 'meta' => ['total' => 0]]);
});

test('returns only the authenticated users liked tracks', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    DiscoveryLikedTrack::factory()->create(['user_id' => $user->id, 'spotify_id' => 'AAAAAAAAAAAAAAAAAAAAAA']);
    DiscoveryLikedTrack::factory()->create(['user_id' => $other->id, 'spotify_id' => 'BBBBBBBBBBBBBBBBBBBBBB']);

    $this->actingAs($user)
        ->getJson(route('discovery.liked-playlist'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.spotify_id', 'AAAAAAAAAAAAAAAAAAAAAA');
});

test('results are ordered by liked_at descending', function () {
    $user = User::factory()->create();

    DiscoveryLikedTrack::factory()->create([
        'user_id' => $user->id,
        'spotify_id' => 'AAAAAAAAAAAAAAAAAAAAAA',
        'liked_at' => now()->subDay(),
    ]);
    DiscoveryLikedTrack::factory()->create([
        'user_id' => $user->id,
        'spotify_id' => 'BBBBBBBBBBBBBBBBBBBBBB',
        'liked_at' => now(),
    ]);

    $this->actingAs($user)
        ->getJson(route('discovery.liked-playlist'))
        ->assertOk()
        ->assertJsonPath('data.0.spotify_id', 'BBBBBBBBBBBBBBBBBBBBBB');
});

test('returns pagination meta', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('discovery.liked-playlist'))
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
});

test('per_page is capped at 50', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('discovery.liked-playlist', ['per_page' => 100]))
        ->assertOk()
        ->assertJsonPath('meta.per_page', 50);
});
