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
        'position' => 10,
    ]);

    Playlist::factory()->create([
        'user_id' => $user->id,
        'folder_id' => null,
        'spotify_id' => 'playlist-2',
        'name' => 'After Hours',
        'tracks_total' => 12,
        'position' => 20,
    ]);

    $this->actingAs($user)
        ->get(route('library.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Library/Index')
            ->where('syncStatus.isRunning', false)
            ->where('hiddenCount', 0)
            ->where('showHidden', false)
            ->where('folders.0.name', 'Road Trip')
            ->has('playlists', 2)
            ->where('playlists', fn ($playlists): bool => collect($playlists)
                ->pluck('id')
                ->sort()
                ->values()
                ->all() === ['playlist-1', 'playlist-2'])
            ->where('playlists', fn ($playlists): bool => collect($playlists)
                ->pluck('position')
                ->every(fn ($position): bool => is_int($position)))
            ->where('playlists', fn ($playlists): bool => collect($playlists)
                ->pluck('is_hidden')
                ->every(fn ($isHidden): bool => is_bool($isHidden)))
        );
});

test('library page hides hidden playlists by default and returns hidden count', function () {
    $user = User::factory()->create();

    Playlist::factory()->create([
        'user_id' => $user->id,
        'spotify_id' => 'playlist-visible-index',
        'is_hidden' => false,
    ]);

    Playlist::factory()->create([
        'user_id' => $user->id,
        'spotify_id' => 'playlist-hidden-index',
        'is_hidden' => true,
    ]);

    $this->actingAs($user)
        ->get(route('library.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Library/Index')
            ->where('showHidden', false)
            ->where('hiddenCount', 1)
            ->where('playlists', fn ($playlists): bool => collect($playlists)
                ->pluck('id')
                ->all() === ['playlist-visible-index']));
});

test('library page includes hidden playlists when show_hidden is enabled', function () {
    $user = User::factory()->create();

    Playlist::factory()->create([
        'user_id' => $user->id,
        'spotify_id' => 'playlist-visible-include-hidden',
        'is_hidden' => false,
    ]);

    Playlist::factory()->create([
        'user_id' => $user->id,
        'spotify_id' => 'playlist-hidden-include-hidden',
        'is_hidden' => true,
    ]);

    $this->actingAs($user)
        ->get(route('library.index', ['show_hidden' => 1]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Library/Index')
            ->where('showHidden', true)
            ->where('hiddenCount', 1)
            ->where('playlists', fn ($playlists): bool => collect($playlists)
                ->pluck('id')
                ->sort()
                ->values()
                ->all() === ['playlist-hidden-include-hidden', 'playlist-visible-include-hidden']));
});
