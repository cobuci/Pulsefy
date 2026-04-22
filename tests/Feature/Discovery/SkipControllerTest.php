<?php

use App\Models\TrackInteraction;
use App\Models\User;

test('guests cannot skip a track', function () {
    $this->postJson(route('discovery.skip'), ['spotify_id' => 'AAAAAAAAAAAAAAAAAAAAAA'])
        ->assertUnauthorized();
});

test('authenticated users can skip a track', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('discovery.skip'), ['spotify_id' => 'AAAAAAAAAAAAAAAAAAAAAA'])
        ->assertOk()
        ->assertJson(['ok' => true, 'skipped' => true]);
});

test('skip records a skip interaction with 14-day suppression', function () {
    $user = User::factory()->create();
    $spotifyId = 'AAAAAAAAAAAAAAAAAAAAAA';

    $this->actingAs($user)->postJson(route('discovery.skip'), ['spotify_id' => $spotifyId]);

    $interaction = TrackInteraction::query()
        ->where('user_id', $user->id)
        ->where('spotify_id', $spotifyId)
        ->where('type', 'skip')
        ->first();

    expect($interaction)->not->toBeNull();
    expect($interaction->expires_at->isAfter(now()->addDays(13)))->toBeTrue();
});

test('skip fails validation with invalid spotify_id format', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('discovery.skip'), ['spotify_id' => 'too-short'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['spotify_id']);
});

test('skip fails validation with missing spotify_id', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('discovery.skip'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['spotify_id']);
});
