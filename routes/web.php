<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LyricsController;
use App\Http\Controllers\NowPlayingController;
use App\Http\Controllers\PlayerControlController;
use App\Http\Controllers\PlayerDeviceController;
use App\Http\Controllers\PlayerDevicesController;
use App\Http\Controllers\PlayerTransferController;
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
    Route::get('player/now-playing', NowPlayingController::class)->name('player.now-playing');
    Route::get('player/lyrics', LyricsController::class)->name('player.lyrics');

    Route::prefix('player')->name('player.')->group(function () {
        Route::post('play', [PlayerControlController::class, 'play'])->name('play');
        Route::post('pause', [PlayerControlController::class, 'pause'])->name('pause');
        Route::post('next', [PlayerControlController::class, 'next'])->name('next');
        Route::post('previous', [PlayerControlController::class, 'previous'])->name('previous');
        Route::get('device-token', PlayerDeviceController::class)->name('device-token');
        Route::get('devices', PlayerDevicesController::class)->name('devices');
        Route::post('transfer', PlayerTransferController::class)->name('transfer');
    });
});

require __DIR__.'/settings.php';
