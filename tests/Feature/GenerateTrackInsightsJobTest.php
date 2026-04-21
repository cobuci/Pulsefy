<?php

use App\Ai\Agents\TrackInsightsAgent;
use App\Enums\TrackInsightStatus;
use App\Events\TrackInsights\TrackInsightsUpdated;
use App\Jobs\GenerateTrackInsightsJob;
use App\Models\TrackInsight;
use App\Services\Lyrics\TrackInsightsService;
use Illuminate\Support\Facades\Event;

test('generate track insights job stores insights and marks as ready', function () {
    Event::fake([TrackInsightsUpdated::class]);

    TrackInsightsAgent::fake([
        [
            'summary_en' => 'A great song.',
            'summary_pt' => 'Uma ótima música.',
            'mood_en' => 'melancholic',
            'mood_pt' => 'melancólico',
            'meaning_en' => 'About loss.',
            'meaning_pt' => 'Sobre perda.',
            'themes_en' => ['love', 'longing'],
            'themes_pt' => ['amor', 'saudade'],
            'trivia_en' => ['Released in 1999'],
            'trivia_pt' => ['Lançada em 1999'],
            'similar_en' => ['Artist X'],
            'similar_pt' => ['Artista X'],
        ],
    ]);

    $insight = TrackInsight::factory()->create([
        'track_id' => 'spotify_insights_job_1',
        'track_name' => 'Hello',
        'artist_name' => 'Adele',
        'album_name' => '25',
        'status' => TrackInsightStatus::Queued,
    ]);

    (new GenerateTrackInsightsJob($insight->id))->handle(app(TrackInsightsService::class));

    $insight->refresh();

    expect($insight->status)->toBe(TrackInsightStatus::Ready)
        ->and($insight->summary)->toBe('A great song.')
        ->and($insight->summary_pt)->toBe('Uma ótima música.')
        ->and($insight->mood)->toBe('melancholic')
        ->and($insight->mood_pt)->toBe('melancólico')
        ->and($insight->meaning)->toBe('About loss.')
        ->and($insight->themes)->toBe(['love', 'longing'])
        ->and($insight->themes_pt)->toBe(['amor', 'saudade'])
        ->and($insight->completed_at)->not->toBeNull();

    TrackInsightsAgent::assertPrompted(fn () => true);

    Event::assertDispatched(TrackInsightsUpdated::class, function (TrackInsightsUpdated $event) use ($insight): bool {
        return $event->broadcastOn()->name === "track-insights.{$insight->track_id}";
    });
});

test('generate track insights job marks as failed on exception', function () {
    Event::fake([TrackInsightsUpdated::class]);

    TrackInsightsAgent::fake(function () {
        throw new RuntimeException('Gemini transport failure');
    });

    $insight = TrackInsight::factory()->create([
        'track_id' => 'spotify_insights_job_fail_1',
        'status' => TrackInsightStatus::Queued,
    ]);

    expect(fn () => (new GenerateTrackInsightsJob($insight->id))->handle(app(TrackInsightsService::class)))
        ->toThrow(RuntimeException::class);

    $insight->refresh();

    expect($insight->status)->toBe(TrackInsightStatus::Failed)
        ->and($insight->error_message)->toContain('Gemini transport failure');

    Event::assertDispatched(TrackInsightsUpdated::class);
});

test('generate track insights job returns early if insight not found', function () {
    Event::fake([TrackInsightsUpdated::class]);

    (new GenerateTrackInsightsJob(99999))->handle(app(TrackInsightsService::class));

    Event::assertNotDispatched(TrackInsightsUpdated::class);
});
