<?php

use App\Jobs\HydrateAlbumPageDataJob;
use App\Jobs\HydrateArtistPageDataJob;
use App\Jobs\RunUserSpotifySyncJob;
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

test('run user spotify sync job has uniqueness and overlap middleware configured', function () {
    $job = new RunUserSpotifySyncJob(123);

    expect($job->uniqueId())->toBe('123')
        ->and($job->uniqueFor)->toBe(300)
        ->and($job->backoff())->toBe([5, 15, 60])
        ->and($job->middleware())->toHaveCount(1);
});
