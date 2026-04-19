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
        $showHidden = request()->boolean('show_hidden');

        return Inertia::render('Library/Index', [
            'folders' => $user->libraryFolders()
                ->orderBy('parent_id')
                ->orderBy('position')
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
                ->when(! $showHidden, fn ($query) => $query->where('is_hidden', false))
                ->orderBy('folder_id')
                ->orderBy('position')
                ->limit(200)
                ->get([
                    'spotify_id',
                    'name',
                    'description',
                    'images',
                    'tracks_total',
                    'owner_name',
                    'synced_at',
                    'folder_id',
                    'position',
                    'is_hidden',
                ])
                ->map(fn (Playlist $playlist): array => [
                    'id' => $playlist->spotify_id,
                    'name' => $playlist->name,
                    'description' => $playlist->description,
                    'image' => data_get($playlist->images, '0.url'),
                    'tracks_total' => $playlist->tracks_total,
                    'owner_name' => $playlist->owner_name,
                    'synced_at' => $playlist->synced_at?->toIso8601String(),
                    'folder_id' => $playlist->folder_id,
                    'position' => (int) data_get($playlist->getAttributes(), 'position', 0),
                    'is_hidden' => (bool) $playlist->is_hidden,
                ])
                ->values(),
            'hiddenCount' => Playlist::query()
                ->whereBelongsTo($user)
                ->where('is_hidden', true)
                ->count(),
            'showHidden' => $showHidden,
            'syncStatus' => $this->statusService->userStatus($user->id),
        ]);
    }
}
