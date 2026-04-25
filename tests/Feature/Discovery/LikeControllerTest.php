<?php

use App\Models\DiscoveryLikedTrack;
use App\Models\Track;
use App\Models\TrackInteraction;
use App\Models\User;

beforeEach(function (): void {
    $this->payload = [
        'spotify_id' => 'AAAAAAAAAAAAAAAAAAAAAA',
        'name' => 'Test Track',
        'artist' => 'Test Artist',
        'album' => 'Test Album',
        'album_art' => null,
    ];
});

test('guests cannot like a track', function (): void {
    $this->postJson(route('discovery.like'), $this->payload)
        ->assertUnauthorized();
});

test('authenticated users can like a track', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('discovery.like'), $this->payload)
        ->assertOk()
        ->assertJson(['ok' => true, 'liked' => true]);
});

test('like saves track to discovery liked tracks', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('discovery.like'), $this->payload);

    $track = Track::query()->where('spotify_id', $this->payload['spotify_id'])->first();

    expect($track)->not->toBeNull()
        ->and(
            DiscoveryLikedTrack::query()
                ->where('user_id', $user->id)
                ->where('track_id', $track->id)
                ->exists()
        )->toBeTrue();
});

test('like records a like interaction', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('discovery.like'), $this->payload);

    $track = Track::query()->where('spotify_id', $this->payload['spotify_id'])->first();

    expect($track)->not->toBeNull()
        ->and(
            TrackInteraction::query()
                ->where('user_id', $user->id)
                ->where('track_id', $track->id)
                ->where('type', 'like')
                ->exists()
        )->toBeTrue();
});

test('like fails validation with invalid spotify_id format', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('discovery.like'), array_merge($this->payload, ['spotify_id' => 'invalid-id']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['spotify_id']);
});

test('like fails validation with missing required fields', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('discovery.like'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['spotify_id', 'name', 'artist', 'album']);
});
