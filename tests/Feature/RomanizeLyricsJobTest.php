<?php

use App\Ai\Agents\LyricsPronunciationAgent;
use App\Events\Lyrics\PronunciationUpdated;
use App\Jobs\RomanizeLyricsJob;
use App\Models\Lyric;
use App\Models\LyricPronunciation;
use App\Models\User;
use App\Services\Lyrics\LyricsPronunciationService;
use Illuminate\Support\Facades\Event;

test('romanize lyrics job stores romanized lines and broadcasts update', function () {
    Event::fake([PronunciationUpdated::class]);

    $user = User::factory()->create();
    $lyric = Lyric::factory()->create([
        'track_id' => 'spotify_track_romanize_job_1',
        'artist_name' => 'Adele',
        'track_name' => 'Hello',
        'synced_lyrics' => "[00:01.00] Hello from the other side\n[00:05.00] I must have called a thousand times",
        'is_synced' => true,
    ]);

    $pronunciation = LyricPronunciation::factory()->create([
        'user_id' => $user->id,
        'lyric_id' => $lyric->id,
        'track_id' => $lyric->track_id,
        'status' => LyricPronunciation::STATUS_QUEUED,
    ]);

    LyricsPronunciationAgent::fake([
        [
            'lines' => [
                [
                    'index' => 0,
                    'timestamp' => '00:01.00',
                    'pt_br' => 'Helou from di adher said',
                    'en' => 'Hello from the other side',
                ],
                [
                    'index' => 1,
                    'timestamp' => '00:05.00',
                    'pt_br' => 'Ai mast hav cold a thausend taims',
                    'en' => 'I must have called a thousand times',
                ],
            ],
        ],
    ]);

    (new RomanizeLyricsJob($pronunciation->id))->handle(app(LyricsPronunciationService::class));

    $pronunciation->refresh();

    expect($pronunciation->status)->toBe(LyricPronunciation::STATUS_READY);
    expect($pronunciation->provider)->toBe('gemini');
    expect($pronunciation->model)->toBe('gemini-3.1-flash-lite-preview');
    expect($pronunciation->romanized_lines)->toBeArray();
    expect($pronunciation->romanized_lines[0]['timestamp'] ?? null)->toBe('00:01.00');

    LyricsPronunciationAgent::assertPrompted(fn () => true);

    Event::assertDispatched(PronunciationUpdated::class, function (PronunciationUpdated $event) use ($user, $lyric): bool {
        return $event->userId === $user->id
            && $event->trackId === $lyric->track_id
            && $event->status === LyricPronunciation::STATUS_READY;
    });
});

test('romanize lyrics job marks pronunciation as failed when ai romanization throws', function () {
    Event::fake([PronunciationUpdated::class]);

    $user = User::factory()->create();
    $lyric = Lyric::factory()->create([
        'track_id' => 'spotify_track_romanize_job_fail_1',
        'artist_name' => 'Adele',
        'track_name' => 'Hello',
        'synced_lyrics' => "[00:01.00] Hello from the other side\n[00:05.00] I must have called a thousand times",
        'is_synced' => true,
    ]);

    $pronunciation = LyricPronunciation::factory()->create([
        'user_id' => $user->id,
        'lyric_id' => $lyric->id,
        'track_id' => $lyric->track_id,
        'status' => LyricPronunciation::STATUS_QUEUED,
    ]);

    LyricsPronunciationAgent::fake(function () {
        throw new RuntimeException('Gemini transport failure');
    });

    expect(fn () => (new RomanizeLyricsJob($pronunciation->id))->handle(app(LyricsPronunciationService::class)))
        ->toThrow(RuntimeException::class);

    $pronunciation->refresh();

    expect($pronunciation->status)->toBe(LyricPronunciation::STATUS_FAILED);
    expect($pronunciation->error_message)->toContain('Gemini transport failure');

    Event::assertDispatched(PronunciationUpdated::class, function (PronunciationUpdated $event) use ($user, $lyric): bool {
        return $event->userId === $user->id
            && $event->trackId === $lyric->track_id
            && $event->status === LyricPronunciation::STATUS_FAILED;
    });
});
