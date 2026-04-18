<?php

use App\Jobs\HydrateAlbumPageDataJob;
use App\Jobs\HydrateArtistPageDataJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('artist show dispatches hydration job to spotify-sync queue', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('artists.show', ['artistId' => 'artist-1']))
        ->assertOk();

    Queue::assertPushed(HydrateArtistPageDataJob::class, function (HydrateArtistPageDataJob $job) use ($user): bool {
        return $job->userId === $user->id && $job->artistSpotifyId === 'artist-1' && $job->queue === 'spotify-sync';
    });
});

test('album show dispatches hydration job to spotify-sync queue', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('albums.show', ['albumId' => 'album-1']))
        ->assertOk();

    Queue::assertPushed(HydrateAlbumPageDataJob::class, function (HydrateAlbumPageDataJob $job) use ($user): bool {
        return $job->userId === $user->id && $job->albumSpotifyId === 'album-1' && $job->queue === 'spotify-sync';
    });
});
