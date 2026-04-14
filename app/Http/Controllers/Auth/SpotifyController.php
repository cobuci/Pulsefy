<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Spotify\SpotifyAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

final class SpotifyController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('spotify')
            ->scopes([
                'user-read-email',
                'user-read-private',
                'user-top-read',
                'user-read-recently-played',
                'user-read-playback-state',
                'user-read-currently-playing',
                'user-modify-playback-state',
                'streaming',
            ])
            ->redirect();
    }

    public function callback(SpotifyAuthService $service): RedirectResponse
    {
        $spotifyUser = Socialite::driver('spotify')->user();

        $user = $service->findOrCreateUser($spotifyUser);

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }
}
