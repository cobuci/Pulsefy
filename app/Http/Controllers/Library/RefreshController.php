<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Services\Spotify\Library\SpotifyLibraryService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class RefreshController extends Controller
{
    public function __construct(
        private readonly SpotifyLibraryService $libraryService,
    ) {}

    public function __invoke(): RedirectResponse
    {
        $user = request()->user();
        $count = $this->libraryService->syncUserPlaylists($user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice('{0} No playlists synced.|{1} :count playlist synced.|[2,*] :count playlists synced.', $count, ['count' => $count]),
        ]);

        return back();
    }
}
