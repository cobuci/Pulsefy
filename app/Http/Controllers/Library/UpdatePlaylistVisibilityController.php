<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\UpdatePlaylistVisibilityRequest;
use App\Models\Playlist;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class UpdatePlaylistVisibilityController extends Controller
{
    public function __invoke(UpdatePlaylistVisibilityRequest $request, string $playlistId): RedirectResponse
    {
        $user = $request->user();

        $playlist = Playlist::query()
            ->whereBelongsTo($user)
            ->where('spotify_id', $playlistId)
            ->firstOrFail();

        $hidden = (bool) $request->validated('hidden');

        $playlist->update([
            'is_hidden' => $hidden,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $hidden ? __('Playlist hidden.') : __('Playlist visible again.'),
        ]);

        return back();
    }
}
