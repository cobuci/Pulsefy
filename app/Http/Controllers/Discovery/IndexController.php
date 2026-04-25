<?php

namespace App\Http\Controllers\Discovery;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateDiscoveryRecommendationsJob;
use App\Models\DailyRecommendation;
use App\Models\TrackInteraction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class IndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $daily = DailyRecommendation::query()
            ->where('user_id', $user->id)
            ->whereDate('date', now()->toDateString())
            ->with('tracks')
            ->first();

        if ($daily === null) {
            GenerateDiscoveryRecommendationsJob::dispatch($user);

            return Inertia::render('Discovery/Index', [
                'status' => 'generating',
                'recommendations' => [],
            ]);
        }

        $interactedIds = TrackInteraction::query()
            ->where('user_id', $user->id)
            ->whereIn('spotify_id', $daily->tracks->pluck('spotify_id'))
            ->pluck('spotify_id')
            ->flip()
            ->all();

        return Inertia::render('Discovery/Index', [
            'status' => 'ready',
            'recommendations' => $daily->tracks
                ->reject(fn ($t) => isset($interactedIds[$t->spotify_id]))
                ->map(fn ($t) => [
                    'spotify_id' => $t->spotify_id,
                    'name' => $t->name,
                    'artist' => $t->artist_name,
                    'album' => $t->album_name,
                    'image_url' => $t->image_url,
                    'match_score' => $t->match_score,
                ])->values()->all(),
        ]);
    }
}
