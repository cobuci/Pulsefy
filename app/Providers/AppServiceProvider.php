<?php

namespace App\Providers;

use App\Services\Spotify\Artist\SpotifyArtistService;
use App\Services\Spotify\Contracts\SpotifyArtistProvider;
use App\Services\Spotify\Contracts\SpotifyPlaybackProvider;
use App\Services\Spotify\Contracts\SpotifyStatsProvider;
use App\Services\Spotify\Playback\SpotifyPlaybackService;
use App\Services\Spotify\SpotifyService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Spotify\SpotifyExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SpotifyStatsProvider::class, SpotifyService::class);
        $this->app->bind(SpotifyArtistProvider::class, SpotifyArtistService::class);
        $this->app->bind(SpotifyPlaybackProvider::class, SpotifyPlaybackService::class);
    }

    public function boot(): void
    {
        $this->configureDefaults();

        Event::listen(SocialiteWasCalled::class, SpotifyExtendSocialite::class);
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::unguard();

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
