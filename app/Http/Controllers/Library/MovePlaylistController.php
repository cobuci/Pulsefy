<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\MovePlaylistRequest;
use App\Models\LibraryFolder;
use App\Models\Playlist;
use Illuminate\Http\RedirectResponse;
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

        $playlist->update([
            'folder_id' => $folderId,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Playlist moved.'),
        ]);

        return back();
    }
}
