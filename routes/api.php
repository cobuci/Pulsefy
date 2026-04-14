<?php

use App\Http\Controllers\Auth\SpotifyController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/spotify/redirect', [SpotifyController::class, 'redirect'])->name('spotify.redirect');
    Route::get('/spotify/callback', [SpotifyController::class, 'callback'])->name('spotify.callback');
});
