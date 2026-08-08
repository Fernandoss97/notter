<?php

use App\Http\Controllers\FocusSessionController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\PomodoroSettingController;
use App\Http\Controllers\StudyPlanController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TopicController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('foco', [FocusSessionController::class, 'show'])->name('focus.show');
    Route::post('foco/start', [FocusSessionController::class, 'start'])->name('focus.start');
    Route::post('foco/pause', [FocusSessionController::class, 'pause'])->name('focus.pause');
    Route::post('foco/resume', [FocusSessionController::class, 'resume'])->name('focus.resume');
    Route::post('foco/complete', [FocusSessionController::class, 'complete'])->name('focus.complete');

    Route::get('plano', [StudyPlanController::class, 'index'])->name('plans.index');
    Route::post('plano', [StudyPlanController::class, 'store'])->name('plans.store');
    Route::post('plano/{plan}/activate', [StudyPlanController::class, 'activate'])->name('plans.activate');
    Route::post('plano/subjects', [SubjectController::class, 'store'])->name('subjects.store');
    Route::post('plano/topics', [TopicController::class, 'store'])->name('topics.store');
    Route::post('plano/topics/{topic}/complete', [TopicController::class, 'complete'])->name('topics.complete');

    Route::get('historico', [HistoryController::class, 'index'])->name('history.index');

    Route::get('configuracoes', [PomodoroSettingController::class, 'edit'])->name('pomodoro-settings.edit');
    Route::put('configuracoes', [PomodoroSettingController::class, 'update'])->name('pomodoro-settings.update');
});
