<?php

use App\Models\Playlist;
use App\Models\User;

test('users can hide and show own playlist', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create([
        'user_id' => $user->id,
        'spotify_id' => 'playlist-visibility-1',
        'is_hidden' => false,
    ]);

    $this->actingAs($user)
        ->patch(route('library.visibility', ['playlistId' => $playlist->spotify_id]), [
            'hidden' => true,
        ])
        ->assertRedirect();

    expect($playlist->fresh()->is_hidden)->toBeTrue();

    $this->actingAs($user)
        ->patch(route('library.visibility', ['playlistId' => $playlist->spotify_id]), [
            'hidden' => false,
        ])
        ->assertRedirect();

    expect($playlist->fresh()->is_hidden)->toBeFalse();
});

test('users cannot update visibility of another user playlist', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $playlist = Playlist::factory()->create([
        'user_id' => $owner->id,
        'spotify_id' => 'playlist-visibility-2',
        'is_hidden' => false,
    ]);

    $this->actingAs($otherUser)
        ->patch(route('library.visibility', ['playlistId' => $playlist->spotify_id]), [
            'hidden' => true,
        ])
        ->assertNotFound();
});

test('visibility update requires boolean hidden payload', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create([
        'user_id' => $user->id,
        'spotify_id' => 'playlist-visibility-3',
    ]);

    $this->actingAs($user)
        ->patch(route('library.visibility', ['playlistId' => $playlist->spotify_id]), [
            'hidden' => 'nope',
        ])
        ->assertSessionHasErrors(['hidden']);
});
