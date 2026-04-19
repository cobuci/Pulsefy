<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Models\Playlist;
use App\Services\Spotify\Library\SpotifyLibraryService;
use Inertia\Inertia;
use Inertia\Response;

final class ShowController extends Controller
{
    public function __construct(
        private readonly SpotifyLibraryService $libraryService,
    ) {}

    public function __invoke(string $playlistId): Response
    {
        $user = request()->user();

        $playlist = Playlist::query()
            ->whereBelongsTo($user)
            ->where('spotify_id', $playlistId)
            ->with('items.track.album')
            ->firstOrFail();

        if (! $playlist->expires_at || $playlist->expires_at->isPast()) {
            $this->libraryService->syncPlaylistTracks($user, $playlist);
            $playlist->refresh();
            $playlist->load('items.track.album');
        }

        return Inertia::render('Library/Show', [
            'playlist' => [
                'id' => $playlist->spotify_id,
                'name' => $playlist->name,
                'description' => $playlist->description,
                'image' => data_get($playlist->images, '0.url'),
                'tracks_total' => $playlist->tracks_total,
                'owner_name' => $playlist->owner_name,
                'synced_at' => $playlist->synced_at?->toIso8601String(),
                'items' => $playlist->items
                    ->map(fn ($item): array => [
                        'spotify_track_id' => $item->spotify_track_id,
                        'position' => $item->position,
                        'added_at' => $item->added_at?->toIso8601String(),
                        'track' => $item->track ? [
                            'id' => $item->track->spotify_id,
                            'name' => $item->track->name,
                            'duration_ms' => $item->track->duration_ms,
                            'image' => data_get($item->track->album?->images, '0.url'),
                        ] : null,
                    ])
                    ->values(),
            ],
        ]);
    }
}
