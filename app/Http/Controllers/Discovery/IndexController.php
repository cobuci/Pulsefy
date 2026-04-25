<?php

namespace App\Http\Controllers\Discovery;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateDiscoveryRecommendationsJob;
use App\Models\DailyRecommendation;
use App\Models\TrackInteraction;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class IndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $daily = DailyRecommendation::query()
            ->where('user_id', $user->id)
            ->whereDate('date', now()->toDateString())
            ->with(['tracks.track.artists', 'tracks.track.album'])
            ->first();

        if ($daily === null) {
            GenerateDiscoveryRecommendationsJob::dispatch($user);

            return Inertia::render('Discovery/Index', [
                'status' => 'generating',
                'recommendations' => [],
            ]);
        }

        $interactedTrackIds = TrackInteraction::query()
            ->where('user_id', $user->id)
            ->whereIn('track_id', $daily->tracks->pluck('track_id'))
            ->pluck('track_id')
            ->flip()
            ->all();

        return Inertia::render('Discovery/Index', [
            'status' => 'ready',
            'recommendations' => $daily->tracks
                ->reject(fn ($rt) => isset($interactedTrackIds[$rt->track_id]))
                ->map(fn ($rt) => [
                    'spotify_id' => $rt->track->spotify_id,
                    'name' => $rt->track->name,
                    /** @phpstan-ignore nullsafe.neverNull */
                    'artist' => $rt->track->artists->first()?->artist_name ?? '',
                    /** @phpstan-ignore nullsafe.neverNull */
                    'album' => $rt->track->album?->name ?? '',
                    'image_url' => $rt->track->image_url,
                    'match_score' => $rt->match_score,
                ])->values()->all(),
        ]);
    }
}
