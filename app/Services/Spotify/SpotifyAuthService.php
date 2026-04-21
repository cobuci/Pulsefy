<?php

namespace App\Services\Spotify;

use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Two\User as SocialiteOAuthUser;

final class SpotifyAuthService
{
    public function findOrCreateUser(SocialiteUser $spotifyUser): User
    {
        /** @var SocialiteOAuthUser $spotifyUser */
        return User::updateOrCreate(
            ['spotify_id' => $spotifyUser->getId()],
            [
                'name' => $spotifyUser->getName(),
                'email' => $spotifyUser->getEmail(),
                'avatar' => $spotifyUser->getAvatar(),
                'spotify_token' => $spotifyUser->token,
                'spotify_refresh_token' => $spotifyUser->refreshToken,
                'spotify_token_expires_at' => Carbon::now()->addSeconds($spotifyUser->expiresIn),
            ]
        );
    }
}
