<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to login for album page', function () {
    $this->get(route('albums.show', ['albumId' => 'album-1']))->assertRedirect(route('login'));
});

test('authenticated users can visit album show page with deferred props', function () {
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
        'api.spotify.com/v1/albums/album-1*' => Http::response([
            'id' => 'album-1',
            'name' => 'Album One',
            'images' => [],
            'release_date' => '2024-01-01',
            'external_urls' => ['spotify' => 'https://open.spotify.com/album/album-1'],
        ]),
        'api.spotify.com/v1/albums/album-1/tracks*' => Http::response([
            'items' => [],
        ]),
    ]);

    $this->actingAs($user)
        ->get(route('albums.show', ['albumId' => 'album-1']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Album/Show')
            ->where('albumId', 'album-1')
            ->missing('album')
            ->missing('tracks')
            ->missing('insights')
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->has('album')
                ->has('tracks')
                ->has('insights')
                ->where('insights.playsLabel', '0')
                ->where('insights.timeLabel', '0m')
                ->where('insights.affinityLabel', '0%')
            )
        );
});
