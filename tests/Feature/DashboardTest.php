<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('dashboard renders with correct inertia component', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->has('period')
        );
});

test('dashboard defaults to medium_term period', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('period', 'medium_term')
        );
});

test('dashboard accepts valid period query param', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard', ['period' => 'short_term']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('period', 'short_term')
        );
});

test('dashboard falls back to medium_term for invalid period', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard', ['period' => 'invalid']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('period', 'medium_term')
        );
});

test('dashboard response includes deferred spotify prop keys', function () {
    $user = User::factory()->create([
        'spotify_token' => 'fake-token',
        'spotify_token_expires_at' => now()->addHour(),
    ]);

    Http::fake([
        'api.spotify.com/v1/me/top/tracks*' => Http::response(['items' => []]),
        'api.spotify.com/v1/me/top/artists*' => Http::response(['items' => []]),
        'api.spotify.com/v1/me/player/recently-played*' => Http::response(['items' => []]),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->missing('topTracks')
            ->missing('topArtists')
            ->missing('recentPlays')
            ->missing('insights')
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->has('topTracks')
                ->has('topArtists')
                ->has('recentPlays')
                ->has('insights')
            )
        );
});
