<?php

namespace App\Http\Controllers\Player;

use App\Enums\TrackInsightStatus;
use App\Events\TrackInsights\TrackInsightsUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Player\StoreTrackInsightRequest;
use App\Jobs\GenerateTrackInsightsJob;
use App\Models\TrackInsight;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TrackInsightsController extends Controller
{
    public function store(StoreTrackInsightRequest $request): JsonResponse
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

    public function regenerate(StoreTrackInsightRequest $request): JsonResponse
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
                'error_message' => 'Previous generation attempt timed out.',
            ]);

            event(TrackInsightsUpdated::fromInsight($existing->refresh()));
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

    public function status(Request $request): JsonResponse
    {
        $request->validate([
            'track_id' => ['required', 'string'],
        ]);

        $insight = TrackInsight::query()
            ->where('track_id', $request->string('track_id'))
            ->first();

        if (! $insight) {
            return response()->json([
                'track_id' => $request->string('track_id')->toString(),
                'status' => null,
                'insights' => null,
                'error_message' => null,
            ]);
        }

        $insights = null;

        if ($insight->status === TrackInsightStatus::Ready) {
            $insights = [
                'summary' => $insight->summary,
                'summary_pt' => $insight->summary_pt,
                'mood' => $insight->mood,
                'mood_pt' => $insight->mood_pt,
                'meaning' => $insight->meaning,
                'meaning_pt' => $insight->meaning_pt,
                'themes' => $insight->themes,
                'themes_pt' => $insight->themes_pt,
                'trivia' => $insight->trivia,
                'trivia_pt' => $insight->trivia_pt,
                'similar' => $insight->similar,
                'similar_pt' => $insight->similar_pt,
            ];
        }

        return response()->json([
            'track_id' => $insight->track_id,
            'status' => $insight->status->value,
            'insights' => $insights,
            'error_message' => $insight->error_message,
        ]);
    }
}
