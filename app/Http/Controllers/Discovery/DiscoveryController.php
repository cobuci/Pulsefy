<?php

namespace App\Http\Controllers\Discovery;

use App\Enums\DailyRecommendationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Discovery\IgnoreTrackRequest;
use App\Http\Requests\Discovery\LikeTrackRequest;
use App\Http\Requests\Discovery\SkipTrackRequest;
use App\Http\Requests\Discovery\UnlikeTrackRequest;
use App\Jobs\GenerateDiscoveryRecommendationsJob;
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

        $processingDaily = $discovery->processingDaily($user);

        if ($processingDaily?->isStale()) {
            $processingDaily->markFailed('Recommendation generation timed out. Please try again.');
            $processingDaily = null;
        }

        $pendingCount = $discovery->pendingCount($user);

        if ($processingDaily !== null && $pendingCount === 0) {
            return $this->generatingResponse();
        }

        if ($discovery->shouldTopUpQueue($user)) {
            $this->dispatchGeneration($discovery, $user);
            $processingDaily = $discovery->processingDaily($user);
        }

        if ($pendingCount > 0) {
            return $this->readyResponse($discovery, $user, $processingDaily !== null);
        }

        if ($processingDaily !== null) {
            return $this->generatingResponse();
        }

        $latestDaily = $discovery->latestDaily($user);

        if ($latestDaily?->status === DailyRecommendationStatus::Failed) {
            return $this->terminalResponse(
                'failed',
                $latestDaily->error_message ?? 'Recommendation generation failed. Please try again.',
            );
        }

        if ($latestDaily?->status === DailyRecommendationStatus::Empty) {
            return $this->terminalResponse('empty');
        }

        $this->dispatchGeneration($discovery, $user);

        return $this->generatingResponse();
    }

    public function retry(Request $request, DiscoveryService $discovery): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($discovery->processingDaily($user) !== null) {
            return redirect()->route('discovery.index');
        }

        $this->dispatchGeneration($discovery, $user);

        return redirect()->route('discovery.index');
    }

    public function like(LikeTrackRequest $request, DiscoveryLikeService $likes): JsonResponse
    {
        $likes->like($request->user(), $request->validated());

        return response()->json(['ok' => true, 'liked' => true]);
    }

    public function unlike(UnlikeTrackRequest $request, DiscoveryLikeService $likes): JsonResponse
    {
        $likes->unlike($request->user(), $request->validated('spotify_id'));

        return response()->json(['ok' => true, 'liked' => false]);
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

    private function dispatchGeneration(DiscoveryService $discovery, User $user): void
    {
        $discovery->beginGeneration($user);
        GenerateDiscoveryRecommendationsJob::dispatch($user);
    }

    private function generatingResponse(): Response
    {
        return Inertia::render('Discovery/Index', [
            'status' => 'generating',
            'recommendations' => [],
            'error_message' => null,
            'can_retry' => false,
            'is_topping_up' => false,
        ]);
    }

    private function readyResponse(DiscoveryService $discovery, User $user, bool $isToppingUp): Response
    {
        return Inertia::render('Discovery/Index', [
            'status' => 'ready',
            'recommendations' => $discovery->pendingRecommendationsForInertia($user),
            'error_message' => null,
            'can_retry' => false,
            'is_topping_up' => $isToppingUp,
        ]);
    }

    private function terminalResponse(string $status, ?string $errorMessage = null): Response
    {
        return Inertia::render('Discovery/Index', [
            'status' => $status,
            'recommendations' => [],
            'error_message' => $errorMessage,
            'can_retry' => true,
            'is_topping_up' => false,
        ]);
    }
}
