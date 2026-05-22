<?php

use App\Jobs\RomanizeLyricsJob;
use App\Models\Lyric;
use App\Models\LyricPronunciation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('guests cannot access romanization endpoint', function () {
    $this->postJson(route('player.lyrics.romanize'), [
        'track_id' => 'track_123',
        'artist' => 'Artist Name',
        'track_name' => 'Song Name',
    ])->assertUnauthorized();
});

test('romanization endpoint queues romanization job when lyrics exist', function () {
    Queue::fake();

    Http::fake([
        'lrclib.net/api/get*' => Http::response([], 404),
        'lrclib.net/api/search*' => Http::response([[
            'syncedLyrics' => "[00:01.00] Hello from the other side\n[00:05.00] I must have called a thousand times",
            'plainLyrics' => "Hello from the other side\nI must have called a thousand times",
        ]], 200),
    ]);

    $user = User::factory()->create();
    $this->actingAs($user)
        ->postJson(route('player.lyrics.romanize'), [
            'track_id' => 'spotify_track_romanize_1',
            'artist' => 'Adele',
            'track_name' => 'Hello',
            'album_name' => '25',
            'duration' => 295000,
        ])
        ->assertStatus(202)
        ->assertJson([
            'ok' => true,
            'track_id' => 'spotify_track_romanize_1',
            'status' => LyricPronunciation::STATUS_QUEUED,
        ]);

    $lyric = Lyric::query()->where('track_id', 'spotify_track_romanize_1')->first();
    expect($lyric)->not->toBeNull();

    $pronunciation = LyricPronunciation::query()
        ->where('user_id', $user->id)
        ->where('track_id', 'spotify_track_romanize_1')
        ->first();

    expect($pronunciation)->not->toBeNull();
    expect($pronunciation?->status)->toBe(LyricPronunciation::STATUS_QUEUED);

    Queue::assertPushed(RomanizeLyricsJob::class, function (RomanizeLyricsJob $job) use ($pronunciation): bool {
        return $pronunciation !== null
            && $job->pronunciationId === $pronunciation->id
            && $job->queue === 'spotify-sync';
    });
});

test('lyrics endpoint includes romanization payload for current user', function () {
    Queue::fake();

    Http::fake([
        'lrclib.net/api/get*' => Http::response([], 404),
        'lrclib.net/api/search*' => Http::response([[
            'syncedLyrics' => '[00:01.00] Hello from the other side',
            'plainLyrics' => 'Hello from the other side',
        ]], 200),
    ]);

    $user = User::factory()->create();
    $this->actingAs($user)->postJson(route('player.lyrics.romanize'), [
        'track_id' => 'spotify_track_romanize_2',
        'artist' => 'Adele',
        'track_name' => 'Hello',
        'album_name' => '25',
        'duration' => 295000,
    ])->assertStatus(202);

    LyricPronunciation::query()
        ->where('user_id', $user->id)
        ->where('track_id', 'spotify_track_romanize_2')
        ->update([
            'status' => LyricPronunciation::STATUS_READY,
            'romanized_lines' => [[
                'index' => 0,
                'timestamp' => '00:01.00',
                'pt_br' => 'Helou from di adher said',
                'en' => 'Hello from the other side',
            ]],
            'provider' => 'gemini',
            'model' => 'gemini-3.1-flash-lite-preview',
            'error_message' => null,
        ]);

    $this->actingAs($user)
        ->getJson(route('player.lyrics', [
            'track_id' => 'spotify_track_romanize_2',
            'artist' => 'Adele',
            'track_name' => 'Hello',
            'album_name' => '25',
            'duration' => 295000,
        ]))
        ->assertOk()
        ->assertJsonPath('romanization.status', LyricPronunciation::STATUS_READY)
        ->assertJsonPath('romanization.provider', 'gemini')
        ->assertJsonPath('romanization.model', 'gemini-3.1-flash-lite-preview')
        ->assertJsonPath('romanization.romanized_lines.0.timestamp', '00:01.00')
        ->assertJsonPath('romanization.romanized_lines.0.pt_br', 'Helou from di adher said');
});

test('romanization endpoint returns validation error when there are no lyrics to romanize', function () {
    Queue::fake();

    Http::fake([
        'lrclib.net/api/get*' => Http::response([], 404),
        'lrclib.net/api/search*' => Http::response([], 200),
    ]);

    $user = User::factory()->create();
    $this->actingAs($user)
        ->postJson(route('player.lyrics.romanize'), [
            'track_id' => 'spotify_track_romanize_none',
            'artist' => 'Unknown Artist',
            'track_name' => 'Unknown Song',
        ])
        ->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'message' => 'Lyrics are not available for romanization.',
        ]);

    Queue::assertNothingPushed();
});

test('stale processing pronunciation is marked failed before re-queuing', function () {
    Queue::fake();

    Http::fake([
        'lrclib.net/api/get*' => Http::response([], 404),
        'lrclib.net/api/search*' => Http::response([[
            'syncedLyrics' => '[00:01.00] Hello',
            'plainLyrics' => 'Hello',
        ]], 200),
    ]);

    $user = User::factory()->create();
    $lyric = Lyric::factory()->create([
        'track_id' => 'spotify_track_stale_romanize',
        'artist_name' => 'Adele',
        'track_name' => 'Hello',
        'synced_lyrics' => '[00:01.00] Hello',
        'plain_lyrics' => 'Hello',
        'is_synced' => true,
    ]);

    LyricPronunciation::factory()->create([
        'user_id' => $user->id,
        'lyric_id' => $lyric->id,
        'track_id' => $lyric->track_id,
        'status' => LyricPronunciation::STATUS_PROCESSING,
        'started_at' => now()->subMinutes(5),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.lyrics.romanize'), [
            'track_id' => 'spotify_track_stale_romanize',
            'artist' => 'Adele',
            'track_name' => 'Hello',
        ])
        ->assertStatus(202)
        ->assertJson(['ok' => true, 'status' => LyricPronunciation::STATUS_QUEUED]);

    $this->assertDatabaseHas('lyric_pronunciations', [
        'user_id' => $user->id,
        'track_id' => 'spotify_track_stale_romanize',
        'status' => LyricPronunciation::STATUS_QUEUED,
    ]);

    Queue::assertPushed(RomanizeLyricsJob::class);
});

test('ready romanization is not re-queued on subsequent requests', function () {
    Queue::fake();

    $user = User::factory()->create();

    $lyric = Lyric::factory()->create([
        'track_id' => 'spotify_track_romanize_ready',
        'artist_name' => 'Adele',
        'track_name' => 'Hello',
        'synced_lyrics' => '[00:01.00] Hello',
        'plain_lyrics' => 'Hello',
        'is_synced' => true,
    ]);

    LyricPronunciation::factory()->ready()->create([
        'user_id' => $user->id,
        'lyric_id' => $lyric->id,
        'track_id' => $lyric->track_id,
    ]);

    $this->actingAs($user)
        ->postJson(route('player.lyrics.romanize'), [
            'track_id' => 'spotify_track_romanize_ready',
            'artist' => 'Adele',
            'track_name' => 'Hello',
        ])
        ->assertStatus(202)
        ->assertJson(['ok' => true, 'status' => LyricPronunciation::STATUS_READY]);

    Queue::assertNothingPushed();
});
