<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\RunUserSpotifySyncJob;
use App\Services\Spotify\SpotifyAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

final class SpotifyController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const array SPOTIFY_SCOPES = [
        'app-remote-control',
        'playlist-modify-private',
        'playlist-modify-public',
        'playlist-read-collaborative',
        'playlist-read-private',
        'streaming',
        'ugc-image-upload',
        'user-follow-modify',
        'user-follow-read',
        'user-library-modify',
        'user-library-read',
        'user-modify-playback-state',
        'user-read-currently-playing',
        'user-read-email',
        'user-read-playback-position',
        'user-read-playback-state',
        'user-read-private',
        'user-read-recently-played',
        'user-top-read',
    ];

    public function redirect(): RedirectResponse
    {
        return Socialite::driver('spotify')
            ->scopes(self::SPOTIFY_SCOPES)
            ->with([
                'show_dialog' => 'true',
            ])
            ->redirect();
    }

    public function callback(SpotifyAuthService $service): RedirectResponse
    {
        $spotifyUser = Socialite::driver('spotify')->user();

        $user = $service->findOrCreateUser($spotifyUser);

        Auth::login($user, remember: true);

        RunUserSpotifySyncJob::dispatch($user->id);

        return redirect()->intended(route('dashboard'));
    }
}
