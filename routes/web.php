<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NowPlayingController;
use App\Http\Controllers\PlayerControlController;
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

    Route::prefix('player')->name('player.')->group(function () {
        Route::post('play', [PlayerControlController::class, 'play'])->name('play');
        Route::post('pause', [PlayerControlController::class, 'pause'])->name('pause');
        Route::post('next', [PlayerControlController::class, 'next'])->name('next');
        Route::post('previous', [PlayerControlController::class, 'previous'])->name('previous');
        Route::post('shuffle', [PlayerControlController::class, 'shuffle'])->name('shuffle');
    });
});

require __DIR__.'/settings.php';
