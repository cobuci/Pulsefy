<?php

use App\Models\Track;
use App\Models\TrackInteraction;
use App\Models\User;

test('guests cannot skip a track', function (): void {
    $this->postJson(route('discovery.skip'), ['spotify_id' => 'AAAAAAAAAAAAAAAAAAAAAA'])
        ->assertUnauthorized();
});

test('authenticated users can skip a track', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('discovery.skip'), ['spotify_id' => 'AAAAAAAAAAAAAAAAAAAAAA'])
        ->assertOk()
        ->assertJson(['ok' => true, 'skipped' => true]);
});

test('skip records a skip interaction with 14-day suppression', function (): void {
    $user = User::factory()->create();
    $spotifyId = 'AAAAAAAAAAAAAAAAAAAAAA';

    $this->actingAs($user)->postJson(route('discovery.skip'), ['spotify_id' => $spotifyId]);

    $track = Track::query()->where('spotify_id', $spotifyId)->first();

    expect($track)->not->toBeNull();

    $interaction = TrackInteraction::query()
        ->where('user_id', $user->id)
        ->where('track_id', $track->id)
        ->where('type', 'skip')
        ->first();

    expect($interaction)->not->toBeNull()
        ->and($interaction->expires_at->isAfter(now()->addDays(13)))->toBeTrue();
});

test('skip fails validation with invalid spotify_id format', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('discovery.skip'), ['spotify_id' => 'too-short'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['spotify_id']);
});

test('skip fails validation with missing spotify_id', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('discovery.skip'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['spotify_id']);
});
