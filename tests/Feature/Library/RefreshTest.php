<?php

use App\Models\User;
use App\Services\Spotify\Library\SpotifyLibraryService;

test('refresh endpoint triggers playlist sync and redirects back', function () {
    $user = User::factory()->create();

    $service = Mockery::mock(SpotifyLibraryService::class);
    $service->shouldReceive('syncUserPlaylists')->once()->andReturn(3);
    app()->instance(SpotifyLibraryService::class, $service);

    $this->actingAs($user)
        ->from(route('library.index'))
        ->post(route('library.refresh'))
        ->assertRedirect(route('library.index'));
});
