<?php

use App\Models\LibraryFolder;
use App\Models\Playlist;
use App\Models\User;

test('users can move own playlist to folder', function () {
    $user = User::factory()->create();
    $folder = LibraryFolder::factory()->create([
        'user_id' => $user->id,
    ]);
    $playlist = Playlist::factory()->create([
        'user_id' => $user->id,
        'folder_id' => null,
        'spotify_id' => 'playlist-move-1',
    ]);

    $this->actingAs($user)
        ->patch(route('library.move', ['playlistId' => 'playlist-move-1']), [
            'folder_id' => $folder->id,
        ])
        ->assertRedirect();

    expect($playlist->fresh()->folder_id)->toBe($folder->id);
    expect($playlist->fresh()->position)->toBeGreaterThan(0);
});

test('users cannot move playlist to folder they do not own', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $folder = LibraryFolder::factory()->create([
        'user_id' => $otherUser->id,
    ]);
    Playlist::factory()->create([
        'user_id' => $user->id,
        'folder_id' => null,
        'spotify_id' => 'playlist-move-2',
    ]);

    $this->actingAs($user)
        ->patch(route('library.move', ['playlistId' => 'playlist-move-2']), [
            'folder_id' => $folder->id,
        ])
        ->assertForbidden();
});
