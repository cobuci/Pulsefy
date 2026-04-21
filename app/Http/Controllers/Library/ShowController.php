<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Jobs\SyncLikedTracksJob;
use App\Jobs\SyncPlaylistTracksJob;
use App\Models\Playlist;
use App\Models\PlaylistTrack;
use App\Models\User;
use App\Services\Spotify\Library\LibrarySyncStatusService;
use Inertia\Inertia;
use Inertia\Response;

final class ShowController extends Controller
{
    private const int PER_PAGE = 50;

    public function __construct(
        private readonly LibrarySyncStatusService $statusService,
    ) {}

    public function __invoke(string $playlistId): Response
    {
        $user = request()->user();

        $playlist = Playlist::query()
            ->whereBelongsTo($user)
            ->where('spotify_id', $playlistId)
            ->firstOrFail();

        $this->maybeDispatchSync($user, $playlist);

        $playlistStatus = $this->statusService->playlistStatus($user->id, $playlist->spotify_id);

        $paginator = PlaylistTrack::query()
            ->where('playlist_id', $playlist->id)
            ->with('track.album', 'track.artists')
            ->orderBy('position')
            ->paginate(self::PER_PAGE)
            ->through(fn (PlaylistTrack $item): array => [
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
            ]);

        return Inertia::render('Library/Show', [
            'playlist' => [
                'id' => $playlist->spotify_id,
                'name' => $playlist->name,
                'description' => $playlist->description,
                'image' => data_get($playlist->images, '0.url'),
                'is_liked_playlist' => (bool) $playlist->is_liked_playlist,
                'tracks_total' => $playlist->tracks_total,
                'owner_name' => $playlist->owner_name,
                'synced_at' => $playlist->synced_at?->toIso8601String(),
                'sync_status' => [
                    'isRunning' => $playlistStatus['isRunning'],
                    'hasFailure' => $playlistStatus['hasFailure'],
                    'updatedAt' => $playlistStatus['updatedAt'],
                ],
            ],
            'items' => Inertia::scroll($paginator),
        ]);
    }

    private function maybeDispatchSync(User $user, Playlist $playlist): void
    {
        $needsSync = ! $playlist->expires_at || $playlist->expires_at->isPast();
        $isEmpty = $playlist->tracks_total === 0;

        if (! $needsSync && ! $isEmpty) {
            return;
        }

        $playlistStatus = $this->statusService->playlistStatus($user->id, $playlist->spotify_id);

        if ($playlistStatus['isRunning']) {
            return;
        }

        $this->statusService->startPlaylistSync($user->id, $playlist->spotify_id);

        if ($playlist->is_liked_playlist) {
            SyncLikedTracksJob::dispatch($user->id)->onQueue('spotify-sync');
        } else {
            SyncPlaylistTracksJob::dispatch($user->id, $playlist->spotify_id)->onQueue('spotify-sync');
        }
    }
}
