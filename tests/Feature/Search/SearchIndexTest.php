<?php

use App\Models\Album;
use App\Models\Artist;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('guests cannot access search endpoint', function () {
    $this->getJson(route('search'))->assertUnauthorized();
});

test('search endpoint returns local quick actions and local entities', function () {
    $user = User::factory()->create();

    $artist = Artist::query()->create([
        'artist_id' => 'artist-local-1',
        'artist_name' => 'Local Aurora',
        'genres' => ['indie'],
        'images' => [['url' => 'https://image.test/artist-local-1.jpg']],
        'fetched_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $album = Album::query()->create([
        'spotify_id' => 'album-local-1',
        'name' => 'Local Aurora Album',
        'album_type' => 'album',
        'release_date' => '2024-01-01',
        'images' => [['url' => 'https://image.test/album-local-1.jpg']],
        'total_tracks' => 8,
        'metadata_synced_at' => now(),
    ]);

    $album->artists()->syncWithoutDetaching([$artist->id]);

    $track = Track::query()->create([
        'spotify_id' => 'track-local-1',
        'album_id' => $album->id,
        'name' => 'Local Aurora Song',
        'duration_ms' => 180000,
        'explicit' => false,
        'metadata_synced_at' => now(),
    ]);

    $track->artists()->syncWithoutDetaching([$artist->id]);

    Http::fake([
        'api.spotify.com/v1/search*' => Http::response([
            'artists' => ['items' => []],
            'albums' => ['items' => []],
            'tracks' => ['items' => []],
        ]),
    ]);

    $response = $this->actingAs($user)->getJson(route('search', ['q' => 'Aurora']));

    $response
        ->assertSuccessful()
        ->assertJsonPath('quick_actions.0.id', 'go-dashboard')
        ->assertJsonPath('artists.0.id', 'artist-local-1')
        ->assertJsonPath('albums.0.id', 'album-local-1')
        ->assertJsonPath('tracks.0.id', 'track-local-1');
});

test('search deduplicates local and spotify items by spotify id', function () {
    $user = User::factory()->create();

    Artist::query()->create([
        'artist_id' => 'artist-dup-1',
        'artist_name' => 'Dup Artist',
        'genres' => ['alt pop'],
        'images' => [['url' => 'https://image.test/local-artist.jpg']],
        'fetched_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    Album::query()->create([
        'spotify_id' => 'album-dup-1',
        'name' => 'Dup Album',
        'album_type' => 'album',
        'release_date' => '2024-02-02',
        'images' => [['url' => 'https://image.test/local-album.jpg']],
        'total_tracks' => 10,
        'metadata_synced_at' => now(),
    ]);

    Track::query()->create([
        'spotify_id' => 'track-dup-1',
        'name' => 'Dup Track',
        'duration_ms' => 200000,
        'explicit' => false,
        'metadata_synced_at' => now(),
    ]);

    Http::fake([
        'api.spotify.com/v1/search*' => Http::response([
            'artists' => [
                'items' => [[
                    'id' => 'artist-dup-1',
                    'name' => 'Dup Artist',
                    'genres' => ['alt pop'],
                    'images' => [['url' => 'https://image.test/remote-artist.jpg']],
                ]],
            ],
            'albums' => [
                'items' => [[
                    'id' => 'album-dup-1',
                    'name' => 'Dup Album',
                    'artists' => [['name' => 'Dup Artist']],
                    'images' => [['url' => 'https://image.test/remote-album.jpg']],
                ]],
            ],
            'tracks' => [
                'items' => [[
                    'id' => 'track-dup-1',
                    'name' => 'Dup Track',
                    'artists' => [['name' => 'Dup Artist']],
                    'album' => [
                        'id' => 'album-dup-1',
                        'images' => [['url' => 'https://image.test/remote-track.jpg']],
                    ],
                ]],
            ],
        ]),
    ]);

    $response = $this->actingAs($user)->getJson(route('search', ['q' => 'Dup']));

    $response->assertSuccessful();

    expect($response->json('artists'))->toHaveCount(1)
        ->and($response->json('albums'))->toHaveCount(1)
        ->and($response->json('tracks'))->toHaveCount(1);
});

test('search hydrates missing albums from local db when track references them', function () {
    $user = User::factory()->create();

    $artist = Artist::query()->create([
        'artist_id' => 'artist-hydrate',
        'artist_name' => 'Hydrated Artist',
        'genres' => ['electro'],
        'images' => [['url' => 'https://image.test/hydrated-artist.jpg']],
        'fetched_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $album = Album::query()->create([
        'spotify_id' => 'album-hydrate',
        'name' => 'Hydrated Album',
        'album_type' => 'album',
        'release_date' => '2024-03-03',
        'images' => [['url' => 'https://image.test/hydrated-album.jpg']],
        'total_tracks' => 9,
        'metadata_synced_at' => now(),
    ]);

    $album->artists()->syncWithoutDetaching([$artist->id]);

    Http::fake([
        'api.spotify.com/v1/search*' => Http::response([
            'artists' => ['items' => []],
            'albums' => ['items' => []],
            'tracks' => [
                'items' => [[
                    'id' => 'track-hydrate',
                    'name' => 'Hydrated Track',
                    'artists' => [['name' => 'Hydrated Artist']],
                    'album' => [
                        'id' => 'album-hydrate',
                        'images' => [['url' => 'https://image.test/track.jpg']],
                    ],
                ]],
            ],
        ]),
    ]);

    $response = $this->actingAs($user)->getJson(route('search', ['q' => 'Hydrated']));

    $response->assertSuccessful();

    $hydratedAlbum = collect($response->json('albums'))
        ->first(fn (array $album): bool => ($album['id'] ?? null) === 'album-hydrate');

    expect($hydratedAlbum)->not->toBeNull()
        ->and(in_array(($hydratedAlbum['source'] ?? ''), ['local', 'local-hydrated'], true))->toBeTrue();
});

test('search links existing album and track to existing artist when payload references artist id', function () {
    $user = User::factory()->create();

    $artist = Artist::query()->create([
        'artist_id' => 'artist-link-1',
        'artist_name' => 'Linked Artist',
        'genres' => ['synth'],
        'images' => [['url' => 'https://image.test/linked-artist.jpg']],
        'fetched_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $album = Album::query()->create([
        'spotify_id' => 'album-link-1',
        'name' => 'Linked Album',
        'album_type' => 'album',
        'release_date' => '2024-05-01',
        'images' => [['url' => 'https://image.test/linked-album.jpg']],
        'total_tracks' => 9,
        'metadata_synced_at' => now(),
    ]);

    $track = Track::query()->create([
        'spotify_id' => 'track-link-1',
        'album_id' => $album->id,
        'name' => 'Linked Track',
        'duration_ms' => 190000,
        'explicit' => false,
        'metadata_synced_at' => now(),
    ]);

    Http::fake([
        'api.spotify.com/v1/search*' => Http::response([
            'artists' => [
                'items' => [[
                    'id' => 'artist-link-1',
                    'name' => 'Linked Artist',
                    'genres' => ['synth'],
                    'images' => [['url' => 'https://image.test/remote-linked-artist.jpg']],
                ]],
            ],
            'albums' => [
                'items' => [[
                    'id' => 'album-link-1',
                    'name' => 'Linked Album',
                    'artists' => [[
                        'id' => 'artist-link-1',
                        'name' => 'Linked Artist',
                    ]],
                    'images' => [['url' => 'https://image.test/remote-linked-album.jpg']],
                ]],
            ],
            'tracks' => [
                'items' => [[
                    'id' => 'track-link-1',
                    'name' => 'Linked Track',
                    'artists' => [[
                        'id' => 'artist-link-1',
                        'name' => 'Linked Artist',
                    ]],
                    'album' => [
                        'id' => 'album-link-1',
                        'images' => [['url' => 'https://image.test/remote-linked-track.jpg']],
                    ],
                ]],
            ],
        ]),
    ]);

    $this->actingAs($user)->getJson(route('search', ['q' => 'Linked']))->assertSuccessful();

    expect($album->fresh()->artists()->whereKey($artist->id)->exists())->toBeTrue()
        ->and($track->fresh()->artists()->whereKey($artist->id)->exists())->toBeTrue();
});
