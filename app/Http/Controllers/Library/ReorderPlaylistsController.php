<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\ReorderPlaylistsRequest;
use App\Models\LibraryFolder;
use App\Models\Playlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;

final class ReorderPlaylistsController extends Controller
{
    public function __invoke(ReorderPlaylistsRequest $request): RedirectResponse
    {
        if (! Schema::hasColumn('playlists', 'position')) {
            return back();
        }

        $user = $request->user();

        $folderId = $request->validated('folder_id');

        if ($folderId !== null) {
            $folder = LibraryFolder::query()->findOrFail($folderId);

            if ($folder->user_id !== $user->id) {
                abort(403);
            }
        }

        $orderedPlaylistIds = collect($request->validated('ordered_playlist_ids'))
            ->unique()
            ->values();

        $playlists = Playlist::query()
            ->whereBelongsTo($user)
            ->where('is_hidden', false)
            ->when(
                $folderId === null,
                fn ($query) => $query->whereNull('folder_id'),
                fn ($query) => $query->where('folder_id', $folderId),
            )
            ->whereIn('spotify_id', $orderedPlaylistIds->all())
            ->get(['id', 'spotify_id']);

        if ($playlists->count() !== $orderedPlaylistIds->count()) {
            abort(422, 'Invalid playlist order payload.');
        }

        $playlistIdsBySpotifyId = $playlists->pluck('id', 'spotify_id');

        $orderedPlaylistIds
            ->values()
            ->each(function (string $spotifyId, int $position) use ($playlistIdsBySpotifyId): void {
                $playlistId = $playlistIdsBySpotifyId->get($spotifyId);

                if (! is_int($playlistId)) {
                    return;
                }

                Playlist::query()
                    ->whereKey($playlistId)
                    ->update(['position' => $position]);
            });

        return back();
    }
}
