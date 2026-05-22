<?php

namespace App\Http\Controllers\Discovery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Discovery\IgnoreTrackRequest;
use App\Http\Requests\Discovery\LikeTrackRequest;
use App\Http\Requests\Discovery\SkipTrackRequest;
use App\Jobs\GenerateDiscoveryRecommendationsJob;
use App\Models\DailyRecommendation;
use App\Models\DiscoveryLikedTrack;
use App\Models\TrackInteraction;
use App\Models\User;
use App\Services\Discovery\DiscoveryLikeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DiscoveryController extends Controller
{
    public function index(Request $request): Response
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

    public function liked(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $total = DiscoveryLikedTrack::query()
            ->where('user_id', $user->id)
            ->count();

        return Inertia::render('Discovery/Liked', [
            'total' => $total,
            'likedTracks' => Inertia::scroll(
                /** @phpstan-ignore return.type */
                fn () => DiscoveryLikedTrack::query()
                    ->where('user_id', $user->id)
                    ->with('track.artists')
                    ->latest('liked_at')
                    ->paginate(50)
                    ->through(fn (DiscoveryLikedTrack $liked) => [
                        'id' => $liked->id,
                        'spotify_id' => $liked->track->spotify_id,
                        'uri' => 'spotify:track:'.$liked->track->spotify_id,
                        'name' => $liked->track->name,
                        'duration_ms' => $liked->track->duration_ms,
                        'image_url' => $liked->track->image_url,
                        /** @phpstan-ignore nullsafe.neverNull */
                        'artists' => $liked->track->artists->map(fn ($a) => [
                            'id' => $a->artist_id,
                            'name' => $a->artist_name,
                        ])->values(),
                        'liked_at' => $liked->liked_at->toDateString(),
                    ])
            ),
        ]);
    }

    public function likedPlaylist(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $perPage = min((int) $request->query('per_page', 20), 50);

        $paginator = DiscoveryLikedTrack::query()
            ->where('user_id', $user->id)
            ->with('track.artists', 'track.album')
            ->latest('liked_at')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn (DiscoveryLikedTrack $liked) => [
                'id' => $liked->id,
                'spotify_id' => $liked->track->spotify_id,
                'name' => $liked->track->name,
                /** @phpstan-ignore nullsafe.neverNull */
                'artist_name' => $liked->track->artists->first()?->artist_name ?? '',
                /** @phpstan-ignore nullsafe.neverNull */
                'album_name' => $liked->track->album?->name ?? '',
                'image_url' => $liked->track->image_url,
                'liked_at' => $liked->liked_at->toDateString(),
            ])->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
