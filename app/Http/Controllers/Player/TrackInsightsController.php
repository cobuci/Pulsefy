<?php

namespace App\Http\Controllers\Player;

use App\Enums\TrackInsightStatus;
use App\Events\TrackInsights\TrackInsightsUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Player\StoreTrackInsightRequest;
use App\Jobs\GenerateTrackInsightsJob;
use App\Models\TrackInsight;
use Illuminate\Http\JsonResponse;

final class TrackInsightsController extends Controller
{
    public function __invoke(StoreTrackInsightRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $existing = TrackInsight::query()
            ->where('track_id', $validated['track_id'])
            ->first();

        if (
            $existing !== null
            && $existing->status === TrackInsightStatus::Processing
            && $existing->started_at !== null
            && $existing->started_at->copy()->addMinutes(3)->isPast()
        ) {
            $existing->update([
                'status' => TrackInsightStatus::Failed,
                'completed_at' => now(),
                'error_message' => 'Previous generation attempt timed out. Please try again.',
            ]);

            event(TrackInsightsUpdated::fromInsight($existing->refresh()));
        }

        if ($existing !== null && $existing->status === TrackInsightStatus::Ready) {
            return response()->json([
                'ok' => true,
                'track_id' => $existing->track_id,
                'status' => $existing->status->value,
                'insights' => [
                    'summary' => $existing->summary,
                    'summary_pt' => $existing->summary_pt,
                    'mood' => $existing->mood,
                    'mood_pt' => $existing->mood_pt,
                    'meaning' => $existing->meaning,
                    'meaning_pt' => $existing->meaning_pt,
                    'themes' => $existing->themes,
                    'themes_pt' => $existing->themes_pt,
                    'trivia' => $existing->trivia,
                    'trivia_pt' => $existing->trivia_pt,
                    'similar' => $existing->similar,
                    'similar_pt' => $existing->similar_pt,
                ],
            ]);
        }

        if ($existing !== null && $existing->status->isPending()) {
            return response()->json([
                'ok' => true,
                'track_id' => $existing->track_id,
                'status' => $existing->status->value,
                'insights' => null,
            ], 202);
        }

        $insight = TrackInsight::query()->updateOrCreate(
            ['track_id' => $validated['track_id']],
            [
                'track_name' => $validated['track_name'],
                'artist_name' => $validated['artist_name'],
                'album_name' => $validated['album_name'] ?? null,
                'status' => TrackInsightStatus::Queued,
                'summary' => null,
                'mood' => null,
                'meaning' => null,
                'themes' => null,
                'trivia' => null,
                'similar' => null,
                'provider' => null,
                'model' => null,
                'error_message' => null,
                'started_at' => null,
                'completed_at' => null,
            ],
        );

        GenerateTrackInsightsJob::dispatch($insight->id);

        return response()->json([
            'ok' => true,
            'track_id' => $insight->track_id,
            'status' => $insight->status->value,
            'insights' => null,
        ], 202);
    }
}
