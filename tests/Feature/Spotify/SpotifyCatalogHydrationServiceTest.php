<?php

use App\Models\Album;
use App\Models\Artist;
use App\Models\Track;
use App\Services\Spotify\Sync\SpotifyCatalogHydrationService;

test('hydrate tracks links track artists and album relationships', function () {
    $service = app(SpotifyCatalogHydrationService::class);

    $service->hydrateTracks([
        [
            'id' => 'track-1',
            'name' => 'Track One',
            'duration_ms' => 180000,
            'explicit' => false,
            'artists' => [
                ['id' => 'artist-1', 'name' => 'Artist One'],
                ['id' => 'artist-2', 'name' => 'Artist Two'],
            ],
            'album' => [
                'id' => 'album-1',
                'name' => 'Album One',
                'album_type' => 'album',
                'release_date' => '2024-01-01',
                'images' => [],
                'total_tracks' => 12,
            ],
        ],
    ]);

    $track = Track::query()->where('spotify_id', 'track-1')->first();
    $album = Album::query()->where('spotify_id', 'album-1')->first();
    $artistOne = Artist::query()->where('artist_id', 'artist-1')->first();
    $artistTwo = Artist::query()->where('artist_id', 'artist-2')->first();

    expect($track)->not->toBeNull()
        ->and($album)->not->toBeNull()
        ->and($artistOne)->not->toBeNull()
        ->and($artistTwo)->not->toBeNull()
        ->and($track?->album_id)->toBe($album?->id)
        ->and($track?->artists()->count())->toBe(2)
        ->and($artistOne?->albums()->where('albums.id', $album?->id)->exists())->toBeTrue();
});

test('hydrate album profile and artists links album to artists', function () {
    $service = app(SpotifyCatalogHydrationService::class);

    $album = $service->hydrateAlbumProfile([
        'id' => 'album-2',
        'name' => 'Album Two',
        'album_type' => 'single',
        'release_date' => '2025-01-01',
        'images' => [],
        'total_tracks' => 1,
    ]);

    expect($album)->not->toBeNull();

    $service->hydrateAlbumArtists($album, [
        'artists' => [
            ['id' => 'artist-3', 'name' => 'Artist Three'],
        ],
    ]);

    $artist = Artist::query()->where('artist_id', 'artist-3')->first();

    expect($artist)->not->toBeNull()
        ->and($artist?->albums()->where('albums.id', $album?->id)->exists())->toBeTrue();
});
