<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to login for artists pages', function () {
    $this->get(route('artists.index'))->assertRedirect(route('login'));
    $this->get(route('artists.show', ['artistId' => 'artist-1']))->assertRedirect(route('login'));
});

test('authenticated users can visit artists index', function () {
    $user = User::factory()->create([
        'spotify_token' => 'fake-token',
        'spotify_token_expires_at' => now()->addHour(),
    ]);

    Http::fake([
        'api.spotify.com/v1/me/top/artists*' => Http::response(['items' => []]),
    ]);

    $this->actingAs($user)
        ->get(route('artists.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Artist/Index')
            ->missing('topArtists')
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->has('topArtists')
            )
        );
});

test('authenticated users can visit artist show page with deferred props', function () {
    $user = User::factory()->create([
        'spotify_token' => 'fake-token',
        'spotify_token_expires_at' => now()->addHour(),
    ]);

    Http::fake([
        'accounts.spotify.com/api/token' => Http::response([
            'access_token' => 'app-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]),
        'api.spotify.com/v1/artists/artist-1' => Http::response([
            'id' => 'artist-1',
            'name' => 'Artist One',
            'images' => [],
            'genres' => [],
            'popularity' => 50,
            'external_urls' => ['spotify' => 'https://open.spotify.com/artist/artist-1'],
        ]),
        'api.spotify.com/v1/artists/artist-1/top-tracks*' => Http::response([
            'tracks' => [[
                'id' => 'track-1',
                'name' => 'Track One',
                'artists' => [[
                    'id' => 'artist-1',
                    'name' => 'Artist One',
                    'external_urls' => ['spotify' => 'https://open.spotify.com/artist/artist-1'],
                ]],
                'album' => [
                    'id' => 'album-1',
                    'name' => 'Album One',
                    'images' => [],
                    'release_date' => '2024-01-01',
                    'external_urls' => ['spotify' => 'https://open.spotify.com/album/album-1'],
                ],
                'duration_ms' => 180000,
                'popularity' => 50,
                'preview_url' => null,
                'external_urls' => ['spotify' => 'https://open.spotify.com/track/track-1'],
            ]],
        ]),
        'api.spotify.com/v1/me/top/artists*' => Http::response([
            'items' => [[
                'id' => 'artist-1',
                'name' => 'Artist One',
                'images' => [],
                'genres' => ['alt pop'],
                'popularity' => 50,
                'external_urls' => ['spotify' => 'https://open.spotify.com/artist/artist-1'],
            ]],
        ]),
        'api.spotify.com/v1/me/player/recently-played*' => Http::response([
            'items' => [],
        ]),
        'api.spotify.com/v1/artists/artist-1/albums*' => Http::response([
            'items' => [],
        ]),
    ]);

    $this->actingAs($user)
        ->get(route('artists.show', ['artistId' => 'artist-1']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Artist/Show')
            ->where('artistId', 'artist-1')
            ->missing('artist')
            ->missing('topTracks')
            ->missing('albums')
            ->missing('insights')
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->has('artist')
                ->has('topTracks')
                ->has('albums')
                ->has('insights')
                ->where('insights.rankLabel', '#1')
                ->where('insights.firstListenLabel', 'Not enough history')
            )
        );
});
