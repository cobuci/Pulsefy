<?php

namespace App\Http\Controllers\Discovery;

use App\Enums\DailyRecommendationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Discovery\IgnoreTrackRequest;
use App\Http\Requests\Discovery\LikeTrackRequest;
use App\Http\Requests\Discovery\SkipTrackRequest;
use App\Jobs\GenerateDiscoveryRecommendationsJob;
use App\Models\DailyRecommendation;
use App\Models\TrackInteraction;
use App\Models\User;
use App\Services\Discovery\DiscoveryLikeService;
use App\Services\Discovery\DiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DiscoveryController extends Controller
{
    public function index(Request $request, DiscoveryService $discovery): Response
    {
        /** @var User $user */
        $user = $request->user();

        $daily = DailyRecommendation::query()
            ->where('user_id', $user->id)
            ->whereDate('date', now()->toDateString())
            ->with(['tracks.track.album'])
            ->first();

        if ($daily === null) {
            $discovery->beginGeneration($user);
            GenerateDiscoveryRecommendationsJob::dispatch($user);

            return $this->generatingResponse();
        }

        if ($daily->isStale()) {
            $daily->markFailed('Recommendation generation timed out. Please try again.');
            $daily->refresh();
        }

        if ($daily->status->isPending()) {
            return $this->generatingResponse();
        }

        if ($daily->status === DailyRecommendationStatus::Failed) {
            return Inertia::render('Discovery/Index', [
                'status' => 'failed',
                'recommendations' => [],
                'error_message' => $daily->error_message ?? 'Recommendation generation failed. Please try again.',
                'can_retry' => true,
            ]);
        }

        if ($daily->status === DailyRecommendationStatus::Empty) {
            return Inertia::render('Discovery/Index', [
                'status' => 'empty',
                'recommendations' => [],
                'error_message' => null,
                'can_retry' => true,
            ]);
        }

        return $this->readyResponse($user, $daily);
    }

    public function retry(Request $request, DiscoveryService $discovery): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $daily = DailyRecommendation::query()
            ->where('user_id', $user->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        if ($daily !== null && $daily->status->isPending()) {
            return redirect()->route('discovery.index');
        }

        $discovery->beginGeneration($user);
        GenerateDiscoveryRecommendationsJob::dispatch($user);

        return redirect()->route('discovery.index');
    }

    public function like(LikeTrackRequest $request, DiscoveryLikeService $likes): JsonResponse
    {
        $likes->like($request->user(), $request->validated());

        return response()->json(['ok' => true, 'liked' => true]);
    }

    public function skip(SkipTrackRequest $request, DiscoveryLikeService $likes): JsonResponse
    {
        $likes->skip($request->user(), $request->validated('spotify_id'));

        return response()->json(['ok' => true, 'skipped' => true]);
    }

    public function ignore(IgnoreTrackRequest $request, DiscoveryLikeService $likes): JsonResponse
    {
        $likes->ignore($request->user(), $request->validated('spotify_id'));

        return response()->json(['ok' => true, 'ignored' => true]);
    }

    private function generatingResponse(): Response
    {
        return Inertia::render('Discovery/Index', [
            'status' => 'generating',
            'recommendations' => [],
            'error_message' => null,
            'can_retry' => false,
        ]);
    }

    private function readyResponse(User $user, DailyRecommendation $daily): Response
    {
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
                    'artist' => $rt->artist_name,
                    /** @phpstan-ignore nullsafe.neverNull */
                    'album' => $rt->track->album?->name ?? '',
                    'image_url' => $rt->track->image_url,
                    'match_score' => $rt->match_score,
                ])->values()->all(),
            'error_message' => null,
            'can_retry' => false,
        ]);
    }
}
