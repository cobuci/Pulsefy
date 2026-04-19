<?php

use App\Models\LibraryFolder;
use App\Models\Playlist;
use App\Models\User;

test('users can reorder own playlists within a folder', function () {
    $user = User::factory()->create();
    $folder = LibraryFolder::factory()->create([
        'user_id' => $user->id,
    ]);

    $playlistA = Playlist::factory()->create([
        'user_id' => $user->id,
        'folder_id' => $folder->id,
        'spotify_id' => 'playlist-order-a',
        'position' => 100,
    ]);
    $playlistB = Playlist::factory()->create([
        'user_id' => $user->id,
        'folder_id' => $folder->id,
        'spotify_id' => 'playlist-order-b',
        'position' => 200,
    ]);
    $playlistC = Playlist::factory()->create([
        'user_id' => $user->id,
        'folder_id' => $folder->id,
        'spotify_id' => 'playlist-order-c',
        'position' => 300,
    ]);

    $this->actingAs($user)
        ->patch(route('library.reorder'), [
            'folder_id' => $folder->id,
            'ordered_playlist_ids' => [
                $playlistC->spotify_id,
                $playlistA->spotify_id,
                $playlistB->spotify_id,
            ],
        ])
        ->assertRedirect();

    expect($playlistC->fresh()->position)->toBe(0)
        ->and($playlistA->fresh()->position)->toBe(1)
        ->and($playlistB->fresh()->position)->toBe(2);
});

test('users cannot reorder playlists in folder they do not own', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $folder = LibraryFolder::factory()->create([
        'user_id' => $otherUser->id,
    ]);

    $playlist = Playlist::factory()->create([
        'user_id' => $user->id,
        'folder_id' => null,
        'spotify_id' => 'playlist-order-x',
    ]);

    $this->actingAs($user)
        ->patch(route('library.reorder'), [
            'folder_id' => $folder->id,
            'ordered_playlist_ids' => [$playlist->spotify_id],
        ])
        ->assertForbidden();
});

test('reorder rejects payload with playlists outside selected folder', function () {
    $user = User::factory()->create();
    $folder = LibraryFolder::factory()->create([
        'user_id' => $user->id,
    ]);

    $playlistInFolder = Playlist::factory()->create([
        'user_id' => $user->id,
        'folder_id' => $folder->id,
        'spotify_id' => 'playlist-order-in-folder',
    ]);
    $playlistOutsideFolder = Playlist::factory()->create([
        'user_id' => $user->id,
        'folder_id' => null,
        'spotify_id' => 'playlist-order-outside',
    ]);

    $this->actingAs($user)
        ->patch(route('library.reorder'), [
            'folder_id' => $folder->id,
            'ordered_playlist_ids' => [
                $playlistInFolder->spotify_id,
                $playlistOutsideFolder->spotify_id,
            ],
        ])
        ->assertUnprocessable();
});

test('users can reorder root playlists', function () {
    $user = User::factory()->create();

    $playlistA = Playlist::factory()->create([
        'user_id' => $user->id,
        'folder_id' => null,
        'spotify_id' => 'playlist-root-a',
        'position' => 100,
    ]);
    $playlistB = Playlist::factory()->create([
        'user_id' => $user->id,
        'folder_id' => null,
        'spotify_id' => 'playlist-root-b',
        'position' => 200,
    ]);

    $this->actingAs($user)
        ->patch(route('library.reorder'), [
            'folder_id' => null,
            'ordered_playlist_ids' => [
                $playlistB->spotify_id,
                $playlistA->spotify_id,
            ],
        ])
        ->assertRedirect();

    expect($playlistB->fresh()->position)->toBe(0)
        ->and($playlistA->fresh()->position)->toBe(1);
});
