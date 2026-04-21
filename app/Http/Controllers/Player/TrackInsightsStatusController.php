<?php

namespace App\Http\Controllers\Player;

use App\Enums\TrackInsightStatus;
use App\Http\Controllers\Controller;
use App\Models\TrackInsight;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TrackInsightsStatusController extends Controller
{
    public function __invoke(Request $request): JsonResponse
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
