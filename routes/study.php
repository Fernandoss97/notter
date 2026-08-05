<?php

use App\Http\Controllers\FocusSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('foco', [FocusSessionController::class, 'show'])->name('focus.show');
    Route::post('foco/start', [FocusSessionController::class, 'start'])->name('focus.start');
    Route::post('foco/pause', [FocusSessionController::class, 'pause'])->name('focus.pause');
    Route::post('foco/resume', [FocusSessionController::class, 'resume'])->name('focus.resume');
    Route::post('foco/complete', [FocusSessionController::class, 'complete'])->name('focus.complete');
});
