<?php

use App\Models\DiscoveryLikedTrack;
use App\Models\TrackInteraction;
use App\Models\User;

$validPayload = fn () => [
    'spotify_id' => 'AAAAAAAAAAAAAAAAAAAAAA',
    'name' => 'Test Track',
    'artist' => 'Test Artist',
    'album' => 'Test Album',
    'album_art' => null,
];

test('guests cannot like a track', function () use ($validPayload) {
    $this->postJson(route('discovery.like'), $validPayload())
        ->assertUnauthorized();
});

test('authenticated users can like a track', function () use ($validPayload) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('discovery.like'), $validPayload())
        ->assertOk()
        ->assertJson(['ok' => true, 'liked' => true]);
});

test('like saves track to discovery liked tracks', function () use ($validPayload) {
    $user = User::factory()->create();
    $payload = $validPayload();

    $this->actingAs($user)->postJson(route('discovery.like'), $payload);

    expect(
        DiscoveryLikedTrack::query()
            ->where('user_id', $user->id)
            ->where('spotify_id', $payload['spotify_id'])
            ->exists()
    )->toBeTrue();
});

test('like records a like interaction', function () use ($validPayload) {
    $user = User::factory()->create();
    $payload = $validPayload();

    $this->actingAs($user)->postJson(route('discovery.like'), $payload);

    expect(
        TrackInteraction::query()
            ->where('user_id', $user->id)
            ->where('spotify_id', $payload['spotify_id'])
            ->where('type', 'like')
            ->exists()
    )->toBeTrue();
});

test('like fails validation with invalid spotify_id format', function () use ($validPayload) {
    $user = User::factory()->create();
    $payload = array_merge($validPayload(), ['spotify_id' => 'invalid-id']);

    $this->actingAs($user)
        ->postJson(route('discovery.like'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['spotify_id']);
});

test('like fails validation with missing required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('discovery.like'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['spotify_id', 'name', 'artist', 'album']);
});
