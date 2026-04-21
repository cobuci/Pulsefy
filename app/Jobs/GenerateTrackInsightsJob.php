<?php

namespace App\Jobs;

use App\Enums\TrackInsightStatus;
use App\Events\TrackInsights\TrackInsightsUpdated;
use App\Models\TrackInsight;
use App\Services\Lyrics\TrackInsightsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GenerateTrackInsightsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public readonly int $insightId)
    {
        $this->onQueue('insights');
    }

    public function handle(TrackInsightsService $service): void
    {
        $insight = TrackInsight::query()->find($this->insightId);

        if (! $insight) {
            return;
        }

        $insight->update([
            'status' => TrackInsightStatus::Processing,
            'started_at' => now(),
            'error_message' => null,
        ]);

        $this->dispatchStatusUpdate($insight);

        try {
            $result = $service->generate(
                artist: $insight->artist_name,
                trackName: $insight->track_name,
                albumName: $insight->album_name ?? '',
            );

            $insight->update([
                'status' => TrackInsightStatus::Ready,
                'summary' => $result['summary_en'],
                'summary_pt' => $result['summary_pt'],
                'mood' => $result['mood_en'],
                'mood_pt' => $result['mood_pt'],
                'meaning' => $result['meaning_en'],
                'meaning_pt' => $result['meaning_pt'],
                'themes' => $result['themes_en'],
                'themes_pt' => $result['themes_pt'],
                'trivia' => $result['trivia_en'],
                'trivia_pt' => $result['trivia_pt'],
                'similar' => $result['similar_en'],
                'similar_pt' => $result['similar_pt'],
                'provider' => $result['provider'],
                'model' => $result['model'],
                'completed_at' => now(),
                'error_message' => null,
            ]);

            $insight->refresh();
        } catch (Throwable $exception) {
            $insight->update([
                'status' => TrackInsightStatus::Failed,
                'completed_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);

            $insight->refresh();
            $this->dispatchStatusUpdate($insight);

            throw $exception;
        }

        $this->dispatchStatusUpdate($insight);
    }

    private function dispatchStatusUpdate(TrackInsight $insight): void
    {
        try {
            event(TrackInsightsUpdated::fromInsight($insight));
        } catch (Throwable $exception) {
            Log::warning('Failed to broadcast track insights status update.', [
                'insight_id' => $insight->id,
                'track_id' => $insight->track_id,
                'status' => $insight->status->value,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
