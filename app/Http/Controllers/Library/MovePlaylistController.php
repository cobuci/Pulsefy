<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\MovePlaylistRequest;
use App\Models\LibraryFolder;
use App\Models\Playlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

final class MovePlaylistController extends Controller
{
    public function __invoke(MovePlaylistRequest $request, string $playlistId): RedirectResponse
    {
        $user = $request->user();

        $playlist = Playlist::query()
            ->whereBelongsTo($user)
            ->where('spotify_id', $playlistId)
            ->firstOrFail();

        $folderId = $request->validated('folder_id');

        if ($folderId !== null) {
            $folder = LibraryFolder::query()->findOrFail($folderId);

            if ($folder->user_id !== $user->id) {
                abort(403);
            }
        }

        $nextPosition = null;

        if (Schema::hasColumn('playlists', 'position')) {
            $maxPositionInTarget = Playlist::query()
                ->whereBelongsTo($user)
                ->when(
                    $folderId === null,
                    fn ($query) => $query->whereNull('folder_id'),
                    fn ($query) => $query->where('folder_id', $folderId),
                )
                ->max('position');

            $nextPosition = (int) ($maxPositionInTarget ?? 0) + 100;
        }

        $payload = [
            'folder_id' => $folderId,
        ];

        if (is_int($nextPosition)) {
            $payload['position'] = $nextPosition;
        }

        $playlist->update($payload);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Playlist moved.'),
        ]);

        return back();
    }
}
