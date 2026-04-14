<?php

use App\Models\Lyric;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('guests cannot access lyrics endpoint', function () {
    $this->getJson(route('player.lyrics', [
        'track_id' => 'track_123',
        'artist' => 'Artist Name',
        'track_name' => 'Song Name',
    ]))->assertUnauthorized();
});

test('returns cached synced lyrics without calling lrclib', function () {
    $user = User::factory()->create();
    $lyric = Lyric::factory()->create([
        'track_id' => 'spotify_track_1',
        'artist_name' => 'Adele',
        'track_name' => 'Hello',
        'synced_lyrics' => "[00:12.00] Hello, it's me",
        'plain_lyrics' => "Hello, it's me",
        'is_synced' => true,
    ]);

    $this->actingAs($user)
        ->getJson(route('player.lyrics', [
            'track_id' => $lyric->track_id,
            'artist' => 'Adele',
            'track_name' => 'Hello',
        ]))
        ->assertOk()
        ->assertJson([
            'track_id' => 'spotify_track_1',
            'type' => 'synced',
            'lyrics' => "[00:12.00] Hello, it's me",
            'synced' => true,
        ]);

    Http::assertNothingSent();
});

test('fetches lyrics from lrclib and caches synced response', function () {
    $user = User::factory()->create();

    Http::fake([
        'lrclib.net/api/get*' => Http::response([], 404),
        'lrclib.net/api/search*' => Http::response([[
            'syncedLyrics' => "[00:01.00] Line 1\n[00:05.00] Line 2",
            'plainLyrics' => "Line 1\nLine 2",
        ]], 200),
    ]);

    $this->actingAs($user)
        ->getJson(route('player.lyrics', [
            'track_id' => 'spotify_track_2',
            'artist' => 'Artist X',
            'track_name' => 'Track Y',
            'album_name' => 'Album Z',
            'duration' => 245000,
        ]))
        ->assertOk()
        ->assertJson([
            'track_id' => 'spotify_track_2',
            'type' => 'synced',
            'lyrics' => "[00:01.00] Line 1\n[00:05.00] Line 2",
            'synced' => true,
        ]);

    $this->assertDatabaseHas('lyrics', [
        'track_id' => 'spotify_track_2',
        'artist_name' => 'Artist X',
        'track_name' => 'Track Y',
        'is_synced' => true,
        'source' => 'lrclib',
    ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/get'));
    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/search'));
});

test('returns plain lyrics when only plain lyrics are available', function () {
    $user = User::factory()->create();

    Http::fake([
        'lrclib.net/api/get*' => Http::response([], 404),
        'lrclib.net/api/search*' => Http::response([[
            'syncedLyrics' => null,
            'plainLyrics' => "Plain line 1\nPlain line 2",
        ]], 200),
    ]);

    $this->actingAs($user)
        ->getJson(route('player.lyrics', [
            'track_id' => 'spotify_track_plain',
            'artist' => 'Artist Plain',
            'track_name' => 'Track Plain',
        ]))
        ->assertOk()
        ->assertJson([
            'track_id' => 'spotify_track_plain',
            'type' => 'plain',
            'lyrics' => "Plain line 1\nPlain line 2",
            'synced' => false,
        ]);

    $this->assertDatabaseHas('lyrics', [
        'track_id' => 'spotify_track_plain',
        'is_synced' => false,
    ]);
});

test('caches no-lyrics result and prevents repeated lrclib calls', function () {
    $user = User::factory()->create();

    Http::fake([
        'lrclib.net/api/get*' => Http::response([], 404),
        'lrclib.net/api/search*' => Http::response([], 200),
    ]);

    $url = route('player.lyrics', [
        'track_id' => 'spotify_track_none',
        'artist' => 'Unknown Artist',
        'track_name' => 'Unknown Song',
    ]);

    $this->actingAs($user)
        ->getJson($url)
        ->assertOk()
        ->assertJson([
            'track_id' => 'spotify_track_none',
            'type' => 'none',
            'lyrics' => null,
            'synced' => false,
        ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'lrclib.net/api/search'));

    Http::fake([
        'lrclib.net/api/get*' => Http::response([], 500),
    ]);

    $this->actingAs($user)
        ->getJson($url)
        ->assertOk()
        ->assertJson([
            'track_id' => 'spotify_track_none',
            'type' => 'none',
            'lyrics' => null,
            'synced' => false,
        ]);

    Http::assertNothingSent();

    $this->assertDatabaseHas('lyrics', [
        'track_id' => 'spotify_track_none',
        'synced_lyrics' => null,
        'plain_lyrics' => null,
        'is_synced' => false,
    ]);
});

test('normalizes artist and track names before querying lrclib', function () {
    $user = User::factory()->create();

    Http::fake([
        'lrclib.net/api/get*' => Http::response([], 404),
        'lrclib.net/api/search*' => Http::response([[
            'syncedLyrics' => null,
            'plainLyrics' => null,
        ]], 200),
    ]);

    $this->actingAs($user)
        ->getJson(route('player.lyrics', [
            'track_id' => 'spotify_track_normalized',
            'artist' => 'Adele (Live)',
            'track_name' => 'Hello (feat. Drake) - Remastered 2011',
        ]))
        ->assertOk();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/search')
            && str_contains($request->url(), 'artist_name=Adele')
            && str_contains($request->url(), 'track_name=Hello');
    });
});

test('returns validation error when required params are missing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('player.lyrics'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'track_id',
            'artist',
            'track_name',
        ]);
});

test('tries first artist when spotify artist list is comma separated', function () {
    $user = User::factory()->create();

    Http::fake([
        'lrclib.net/api/get*' => function ($request) {
            $query = [];
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

            if (($query['artist_name'] ?? null) === 'Artist One, Artist Two') {
                return Http::response([], 404);
            }

            if (($query['artist_name'] ?? null) === 'Artist One') {
                return Http::response([
                    'syncedLyrics' => null,
                    'plainLyrics' => 'Found with first artist fallback',
                ], 200);
            }

            return Http::response([], 404);
        },
        'lrclib.net/api/search*' => function ($request) {
            $query = [];
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

            if (($query['artist_name'] ?? null) === 'Artist One') {
                return Http::response([[
                    'syncedLyrics' => null,
                    'plainLyrics' => 'Found with first artist fallback',
                ]], 200);
            }

            return Http::response([], 200);
        },
    ]);

    $this->actingAs($user)
        ->getJson(route('player.lyrics', [
            'track_id' => 'spotify_track_fallback_artist',
            'artist' => 'Artist One, Artist Two',
            'track_name' => 'Song Title',
        ]))
        ->assertOk()
        ->assertJson([
            'type' => 'plain',
            'lyrics' => 'Found with first artist fallback',
            'synced' => false,
        ]);
});

test('retries stale negative cache records', function () {
    $user = User::factory()->create();

    Lyric::factory()->noLyrics()->create([
        'track_id' => 'spotify_track_stale_negative',
        'artist_name' => 'Artist Old',
        'track_name' => 'Song Old',
        'source' => 'lrclib',
        'fetched_at' => now()->subDays(8),
    ]);

    Http::fake([
        'lrclib.net/api/get*' => Http::response([], 404),
        'lrclib.net/api/search*' => Http::response([[
            'syncedLyrics' => null,
            'plainLyrics' => 'Recovered after stale cache retry',
        ]], 200),
    ]);

    $this->actingAs($user)
        ->getJson(route('player.lyrics', [
            'track_id' => 'spotify_track_stale_negative',
            'artist' => 'Artist Old',
            'track_name' => 'Song Old',
        ]))
        ->assertOk()
        ->assertJson([
            'type' => 'plain',
            'lyrics' => 'Recovered after stale cache retry',
            'synced' => false,
        ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'lrclib.net/api/search'));
});

test('force refresh bypasses cache and refetches lyrics', function () {
    $user = User::factory()->create();

    Lyric::factory()->noLyrics()->create([
        'track_id' => 'spotify_track_force_refresh',
        'artist_name' => 'Artist Force',
        'track_name' => 'Song Force',
        'source' => 'lrclib',
        'fetched_at' => now(),
    ]);

    Http::fake([
        'lrclib.net/api/get*' => Http::response([], 404),
        'lrclib.net/api/search*' => Http::response([[
            'syncedLyrics' => null,
            'plainLyrics' => 'Found after force refresh',
        ]], 200),
    ]);

    $this->actingAs($user)
        ->getJson(route('player.lyrics', [
            'track_id' => 'spotify_track_force_refresh',
            'artist' => 'Artist Force',
            'track_name' => 'Song Force',
            'force_refresh' => 1,
        ]))
        ->assertOk()
        ->assertJson([
            'type' => 'plain',
            'lyrics' => 'Found after force refresh',
            'synced' => false,
        ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'lrclib.net/api/search'));
});
