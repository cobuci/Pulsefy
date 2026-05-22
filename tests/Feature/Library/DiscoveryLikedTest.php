<?php

use App\Models\DiscoveryLikedTrack;
use App\Models\Track;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('guests cannot access discovery liked playlist', function () {
    $this->get(route('library.discovery-liked'))
        ->assertRedirect(route('login'));
});

test('playlist page includes artist name on liked tracks', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create(['spotify_id' => 'AAAAAAAAAAAAAAAAAAAAAA', 'name' => 'Test Track']);

    DiscoveryLikedTrack::factory()->create([
        'user_id' => $user->id,
        'track_id' => $track->id,
        'artist_name' => 'Test Artist',
    ]);

    $this->actingAs($user)
        ->get(route('library.discovery-liked'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Library/DiscoveryLiked')
            ->has('likedTracks.data', 1)
            ->where('likedTracks.data.0.artist_name', 'Test Artist')
        );
});

test('legacy discovery liked route redirects to library', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/discovery/liked')
        ->assertRedirect('/library/discovery-liked');
});
