<?php

use App\Models\Track;
use App\Models\TrackInteraction;
use App\Models\User;

test('guests cannot ignore a track', function (): void {
    $this->postJson(route('discovery.ignore'), ['spotify_id' => 'AAAAAAAAAAAAAAAAAAAAAA'])
        ->assertUnauthorized();
});

test('authenticated users can ignore a track', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('discovery.ignore'), ['spotify_id' => 'AAAAAAAAAAAAAAAAAAAAAA'])
        ->assertOk()
        ->assertJson(['ok' => true, 'ignored' => true]);
});

test('ignore records a permanent interaction with no expiry', function (): void {
    $user = User::factory()->create();
    $spotifyId = 'AAAAAAAAAAAAAAAAAAAAAA';

    $this->actingAs($user)->postJson(route('discovery.ignore'), ['spotify_id' => $spotifyId]);

    $track = Track::query()->where('spotify_id', $spotifyId)->first();

    expect($track)->not->toBeNull();

    $interaction = TrackInteraction::query()
        ->where('user_id', $user->id)
        ->where('track_id', $track->id)
        ->where('type', 'ignore')
        ->first();

    expect($interaction)->not->toBeNull()
        ->and($interaction->expires_at)->toBeNull();
});

test('ignored track is included in suppression set', function (): void {
    $user = User::factory()->create();
    $spotifyId = 'AAAAAAAAAAAAAAAAAAAAAA';

    $this->actingAs($user)->postJson(route('discovery.ignore'), ['spotify_id' => $spotifyId]);

    $track = Track::query()->where('spotify_id', $spotifyId)->first();

    $suppressed = TrackInteraction::query()
        ->suppressedForUser($user->id)
        ->join('tracks', 'tracks.id', '=', 'track_interactions.track_id')
        ->pluck('tracks.spotify_id');

    expect($suppressed)->toContain($spotifyId);
});

test('ignore fails validation with invalid spotify_id format', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('discovery.ignore'), ['spotify_id' => 'too-short'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['spotify_id']);
});

test('ignore fails validation with missing spotify_id', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('discovery.ignore'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['spotify_id']);
});
