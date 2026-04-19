<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Jobs\SyncPlaylistTracksJob;
use App\Models\Playlist;
use App\Services\Spotify\Library\LibrarySyncStatusService;
use Inertia\Inertia;
use Inertia\Response;

final class ShowController extends Controller
{
    public function __construct(
        private readonly LibrarySyncStatusService $statusService,
    ) {}

    public function __invoke(string $playlistId): Response
    {
        $user = request()->user();

        $playlist = Playlist::query()
            ->whereBelongsTo($user)
            ->where('spotify_id', $playlistId)
            ->with('items.track.album', 'items.track.artists')
            ->firstOrFail();

        if (! $playlist->expires_at || $playlist->expires_at->isPast()) {
            $playlistStatus = $this->statusService->playlistStatus($user->id, $playlist->spotify_id);

            if (! $playlistStatus['isRunning']) {
                SyncPlaylistTracksJob::dispatch($user->id, $playlist->spotify_id)->onQueue('spotify-sync');
                $this->statusService->startPlaylistSync($user->id, $playlist->spotify_id);
            }
        }

        if ($playlist->items->isEmpty()) {
            $playlistStatus = $this->statusService->playlistStatus($user->id, $playlist->spotify_id);

            if (! $playlistStatus['isRunning']) {
                SyncPlaylistTracksJob::dispatch($user->id, $playlist->spotify_id)->onQueue('spotify-sync');
                $this->statusService->startPlaylistSync($user->id, $playlist->spotify_id);
            }
        }

        $playlistStatus = $this->statusService->playlistStatus($user->id, $playlist->spotify_id);

        return Inertia::render('Library/Show', [
            'playlist' => [
                'id' => $playlist->spotify_id,
                'name' => $playlist->name,
                'description' => $playlist->description,
                'image' => data_get($playlist->images, '0.url'),
                'tracks_total' => $playlist->tracks_total,
                'owner_name' => $playlist->owner_name,
                'synced_at' => $playlist->synced_at?->toIso8601String(),
                'sync_status' => [
                    'isRunning' => $playlistStatus['isRunning'],
                    'hasFailure' => $playlistStatus['hasFailure'],
                    'updatedAt' => $playlistStatus['updatedAt'],
                ],
                'items' => $playlist->items
                    ->map(fn ($item): array => [
                        'spotify_track_id' => $item->spotify_track_id,
                        'uri' => $item->track?->spotify_id ? 'spotify:track:'.$item->track->spotify_id : null,
                        'position' => $item->position,
                        'added_at' => $item->added_at?->toIso8601String(),
                        'track' => $item->track ? [
                            'id' => $item->track->spotify_id,
                            'name' => $item->track->name,
                            'duration_ms' => $item->track->duration_ms,
                            'image' => data_get($item->track->album?->images, '0.url'),
                            'artists' => $item->track->artists
                                ->map(fn ($artist): array => [
                                    'id' => $artist->artist_id,
                                    'name' => $artist->artist_name,
                                ])
                                ->values(),
                        ] : null,
                    ])
                    ->values(),
            ],
        ]);
    }
}
