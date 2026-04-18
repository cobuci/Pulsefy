<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\ReverbTestController;
use App\Http\Controllers\Settings\ReverbToastTestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
    Route::get('settings/reverb-test', ReverbTestController::class)->name('reverb-test.edit');
    Route::post('settings/reverb-test/dispatch-toast', ReverbToastTestController::class)->name('reverb-test.dispatch-toast');
});
