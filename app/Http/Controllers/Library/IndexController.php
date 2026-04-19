<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Models\Playlist;
use App\Services\Spotify\Library\LibrarySyncStatusService;
use Inertia\Inertia;
use Inertia\Response;

final class IndexController extends Controller
{
    public function __construct(
        private readonly LibrarySyncStatusService $statusService,
    ) {}

    public function __invoke(): Response
    {
        $user = request()->user();

        return Inertia::render('Library/Index', [
            'folders' => $user->libraryFolders()
                ->get()
                ->map(fn ($folder): array => [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'parent_id' => $folder->parent_id,
                    'position' => $folder->position,
                ])
                ->values(),
            'playlists' => Playlist::query()
                ->whereBelongsTo($user)
                ->orderByDesc('updated_at')
                ->limit(200)
                ->get()
                ->map(fn (Playlist $playlist): array => [
                    'id' => $playlist->spotify_id,
                    'name' => $playlist->name,
                    'description' => $playlist->description,
                    'image' => data_get($playlist->images, '0.url'),
                    'tracks_total' => $playlist->tracks_total,
                    'owner_name' => $playlist->owner_name,
                    'synced_at' => $playlist->synced_at?->toIso8601String(),
                    'folder_id' => $playlist->folder_id,
                ])
                ->values(),
            'syncStatus' => $this->statusService->userStatus($user->id),
        ]);
    }
}
