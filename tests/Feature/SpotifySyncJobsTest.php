<?php

use App\Jobs\BackfillArtistGenresJob;
use App\Jobs\HydrateAlbumPageDataJob;
use App\Jobs\HydrateArtistPageDataJob;
use App\Jobs\RunUserSpotifySyncJob;
use App\Models\Artist;
use App\Models\User;
use App\Services\LastFm\LastFmClient;
use App\Services\LastFm\LastFmGenreService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('artist show dispatches hydration job to spotify-sync queue', function () {
    Queue::fake();
    Cache::flush();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('artists.show', ['artistId' => 'artist-1']))
        ->assertOk();

    Queue::assertPushed(HydrateArtistPageDataJob::class, function (HydrateArtistPageDataJob $job) use ($user): bool {
        return $job->userId === $user->id && $job->artistSpotifyId === 'artist-1' && $job->queue === 'spotify-sync';
    });
});

test('artist show dispatches only one hydration job during cooldown window', function () {
    Queue::fake();
    Cache::flush();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('artists.show', ['artistId' => 'artist-1']))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('artists.show', ['artistId' => 'artist-1']))
        ->assertOk();

    Queue::assertPushed(HydrateArtistPageDataJob::class, 1);
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

test('backfill artist genres job fills missing genres from lastfm', function () {
    $artist = Artist::query()->create([
        'artist_id' => 'artist-without-genre',
        'artist_name' => 'Artist Without Genre',
        'genres' => [],
        'fetched_at' => now()->subDay(),
        'expires_at' => now()->subHour(),
    ]);

    config()->set('services.lastfm.api_key', 'test-key');

    Http::fake([
        'ws.audioscrobbler.com/2.0/*' => Http::response([
            'toptags' => [
                'tag' => [
                    ['name' => 'Indie Pop', 'count' => 100],
                ],
            ],
        ]),
    ]);

    $lastFm = new LastFmGenreService(new LastFmClient);

    (new BackfillArtistGenresJob)->handle($lastFm);

    $artist->refresh();

    expect($artist->genres)->toBe(['indie pop'])
        ->and($artist->lastfm_genres_checked_at)->not->toBeNull()
        ->and($artist->expires_at->isFuture())->toBeTrue();
});

test('backfill artist genres job stores check timestamp even when no genre found', function () {
    $artist = Artist::query()->create([
        'artist_id' => 'artist-no-result',
        'artist_name' => 'Artist No Result',
        'genres' => [],
        'fetched_at' => now()->subDay(),
        'expires_at' => now()->subHour(),
    ]);

    config()->set('services.lastfm.api_key', 'test-key');

    Http::fake([
        'ws.audioscrobbler.com/2.0/*' => Http::response([
            'toptags' => [
                'tag' => [],
            ],
        ]),
    ]);

    $lastFm = new LastFmGenreService(new LastFmClient);

    (new BackfillArtistGenresJob)->handle($lastFm);

    $artist->refresh();

    expect($artist->genres)->toBe([])
        ->and($artist->lastfm_genres_checked_at)->not->toBeNull();
});

test('backfill artist genres job skips artists inside cooldown window', function () {
    Artist::query()->create([
        'artist_id' => 'artist-cooldown',
        'artist_name' => 'Artist Cooldown',
        'genres' => [],
        'fetched_at' => now()->subDay(),
        'expires_at' => now()->subHour(),
        'lastfm_genres_checked_at' => now()->subHours(2),
    ]);

    config()->set('services.lastfm.api_key', 'test-key');

    Http::fake();

    $lastFm = new LastFmGenreService(new LastFmClient);

    (new BackfillArtistGenresJob)->handle($lastFm);

    Http::assertNothingSent();
});

test('backfill artist genres job has uniqueness and overlap middleware configured', function () {
    $job = new BackfillArtistGenresJob;

    expect($job->uniqueId())->toBe('artist-genres-backfill')
        ->and($job->uniqueFor)->toBe(900)
        ->and($job->backoff())->toBe([10, 60, 180])
        ->and($job->middleware())->toHaveCount(1);
});

test('console scheduler registers artist genres backfill job', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('BackfillArtistGenresJob')
        ->assertSuccessful();
});
