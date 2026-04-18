<?php

use App\Http\Controllers\Album\ShowController as AlbumShowController;
use App\Http\Controllers\Artist\IndexController as ArtistIndexController;
use App\Http\Controllers\Artist\ShowController as ArtistShowController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Insights\RefreshController as InsightsRefreshController;
use App\Http\Controllers\Player\ControlController as PlayerControlController;
use App\Http\Controllers\Player\DevicesController as PlayerDevicesController;
use App\Http\Controllers\Player\DeviceTokenController;
use App\Http\Controllers\Player\LyricsController;
use App\Http\Controllers\Player\NowPlayingController;
use App\Http\Controllers\Player\TransferController as PlayerTransferController;
use App\Http\Controllers\RecentlyPlayedController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('recently-played', RecentlyPlayedController::class)->name('recently-played');
    Route::get('artists', ArtistIndexController::class)->name('artists.index');
    Route::get('artists/{artistId}', ArtistShowController::class)->name('artists.show');
    Route::get('albums/{albumId}', AlbumShowController::class)->name('albums.show');
    Route::post('insights/refresh', InsightsRefreshController::class)->name('insights.refresh');
    Route::get('player/now-playing', NowPlayingController::class)->name('player.now-playing');
    Route::get('player/lyrics', LyricsController::class)->name('player.lyrics');

    Route::prefix('player')->name('player.')->group(function () {
        Route::post('play', [PlayerControlController::class, 'play'])->name('play');
        Route::post('pause', [PlayerControlController::class, 'pause'])->name('pause');
        Route::post('next', [PlayerControlController::class, 'next'])->name('next');
        Route::post('previous', [PlayerControlController::class, 'previous'])->name('previous');
        Route::post('seek', [PlayerControlController::class, 'seek'])->name('seek');
        Route::post('volume', [PlayerControlController::class, 'volume'])->name('volume');
        Route::post('shuffle', [PlayerControlController::class, 'shuffle'])->name('shuffle');
        Route::post('repeat', [PlayerControlController::class, 'repeat'])->name('repeat');
        Route::get('device-token', DeviceTokenController::class)->name('device-token');
        Route::get('devices', PlayerDevicesController::class)->name('devices');
        Route::post('transfer', PlayerTransferController::class)->name('transfer');
    });
});

require __DIR__.'/settings.php';
