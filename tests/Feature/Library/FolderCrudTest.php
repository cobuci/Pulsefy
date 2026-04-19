<?php

use App\Models\LibraryFolder;
use App\Models\Playlist;
use App\Models\User;

test('authenticated users can create folders in library', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('library.folders.store'), [
            'name' => 'Workout',
        ])
        ->assertRedirect();

    expect(LibraryFolder::query()
        ->where('user_id', $user->id)
        ->where('name', 'Workout')
        ->exists())->toBeTrue();
});

test('users can rename own folder', function () {
    $user = User::factory()->create();
    $folder = LibraryFolder::factory()->create([
        'user_id' => $user->id,
        'name' => 'Old Name',
    ]);

    $this->actingAs($user)
        ->patch(route('library.folders.update', $folder), [
            'name' => 'New Name',
        ])
        ->assertRedirect();

    expect($folder->fresh()->name)->toBe('New Name');
});

test('users cannot update folder they do not own', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $folder = LibraryFolder::factory()->create([
        'user_id' => $owner->id,
    ]);

    $this->actingAs($otherUser)
        ->patch(route('library.folders.update', $folder), [
            'name' => 'Should Fail',
        ])
        ->assertForbidden();
});

test('deleting a folder moves its playlists to root', function () {
    $user = User::factory()->create();
    $folder = LibraryFolder::factory()->create([
        'user_id' => $user->id,
    ]);

    $playlist = Playlist::factory()->create([
        'user_id' => $user->id,
        'folder_id' => $folder->id,
    ]);

    $this->actingAs($user)
        ->delete(route('library.folders.destroy', $folder))
        ->assertRedirect();

    expect($playlist->fresh()->folder_id)->toBeNull();
});
