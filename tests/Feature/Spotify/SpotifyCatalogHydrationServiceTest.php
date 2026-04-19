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

test('hydrate artist profile persists metadata used by frontend', function () {
    $service = app(SpotifyCatalogHydrationService::class);

    $artist = $service->hydrateArtistProfile([
        'id' => 'artist-meta',
        'name' => 'Artist Meta',
        'genres' => ['metalcore'],
        'images' => [[
            'url' => 'https://example.com/artist-meta.jpg',
            'height' => 640,
            'width' => 640,
        ]],
        'popularity' => 87,
        'uri' => 'spotify:artist:artist-meta',
        'external_urls' => [
            'spotify' => 'https://open.spotify.com/artist/artist-meta',
        ],
    ]);

    expect($artist)->not->toBeNull()
        ->and($artist?->images)->toBeArray()
        ->and(data_get($artist?->images, '0.url'))->toBe('https://example.com/artist-meta.jpg')
        ->and($artist?->popularity)->toBe(87)
        ->and($artist?->uri)->toBe('spotify:artist:artist-meta')
        ->and(data_get($artist?->external_urls, 'spotify'))->toBe('https://open.spotify.com/artist/artist-meta');
});

test('hydrate album profile preserves existing metadata when payload is partial', function () {
    $existing = Album::query()->create([
        'spotify_id' => 'album-preserve',
        'name' => 'Album Preserve',
        'album_type' => 'album',
        'release_date' => '2023-01-01',
        'images' => [['url' => 'https://image.test/album-preserve.jpg']],
        'total_tracks' => 11,
        'metadata_synced_at' => now()->subDay(),
    ]);

    $service = app(SpotifyCatalogHydrationService::class);

    $album = $service->hydrateAlbumProfile([
        'id' => 'album-preserve',
        'name' => 'Album Preserve Updated',
    ]);

    expect($album)->not->toBeNull();

    $existing->refresh();

    expect($existing->name)->toBe('Album Preserve Updated')
        ->and($existing->album_type)->toBe('album')
        ->and($existing->release_date)->toBe('2023-01-01')
        ->and(data_get($existing->images, '0.url'))->toBe('https://image.test/album-preserve.jpg')
        ->and($existing->total_tracks)->toBe(11);
});
