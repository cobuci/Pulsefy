<?php

use App\Models\LibraryFolder;
use App\Models\Playlist;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('guests are redirected from library page', function () {
    $this->get(route('library.index'))->assertRedirect(route('login'));
});

test('authenticated users can view library page with folders and root playlists', function () {
    $user = User::factory()->create();

    LibraryFolder::factory()->create([
        'user_id' => $user->id,
        'name' => 'Road Trip',
        'position' => 1,
    ]);

    Playlist::factory()->create([
        'user_id' => $user->id,
        'folder_id' => null,
        'spotify_id' => 'playlist-1',
        'name' => 'Top Vibes',
        'tracks_total' => 24,
    ]);

    $this->actingAs($user)
        ->get(route('library.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Library/Index')
            ->where('folders.0.name', 'Road Trip')
            ->where('playlists.0.id', 'playlist-1')
            ->where('playlists.0.name', 'Top Vibes')
            ->where('playlists.0.tracks_total', 24)
        );
});
