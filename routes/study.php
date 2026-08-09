<?php

use App\Http\Controllers\FocusSessionController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\PomodoroSettingController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\StudyPlanController;
use App\Http\Controllers\StudyTrackController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TopicController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('foco', [FocusSessionController::class, 'show'])->name('focus.show');
    Route::post('foco/start', [FocusSessionController::class, 'start'])->name('focus.start');
    Route::post('foco/pause', [FocusSessionController::class, 'pause'])->name('focus.pause');
    Route::post('foco/resume', [FocusSessionController::class, 'resume'])->name('focus.resume');
    Route::post('foco/advance', [FocusSessionController::class, 'advance'])->name('focus.advance');
    Route::post('foco/extend', [FocusSessionController::class, 'extend'])->name('focus.extend');
    Route::post('foco/complete', [FocusSessionController::class, 'complete'])->name('focus.complete');
    Route::post('foco/notes', [FocusSessionController::class, 'notes'])->name('focus.notes');

    Route::get('plano', [StudyPlanController::class, 'index'])->name('plans.index');
    Route::post('plano', [StudyPlanController::class, 'store'])->name('plans.store');
    Route::put('plano/{plan}', [StudyPlanController::class, 'update'])->name('plans.update');
    Route::post('plano/{plan}/activate', [StudyPlanController::class, 'activate'])->name('plans.activate');
    Route::post('plano/subjects', [SubjectController::class, 'store'])->name('subjects.store');
    Route::post('plano/topics', [TopicController::class, 'store'])->name('topics.store');
    Route::post('plano/topics/{topic}/complete', [TopicController::class, 'complete'])->name('topics.complete');
    Route::post('plano/topics/{topic}/uncomplete', [TopicController::class, 'uncomplete'])->name('topics.uncomplete');
    Route::put('plano/topics/{topic}/questoes', [TopicController::class, 'exercises'])->name('topics.exercises');

    Route::get('trilha', [StudyTrackController::class, 'index'])->name('track.index');
    Route::post('trilha/importar', [StudyTrackController::class, 'import'])->name('track.import');

    Route::post('revisoes/{review}/complete', [ReviewController::class, 'complete'])->name('reviews.complete');

    Route::get('historico', [HistoryController::class, 'index'])->name('history.index');

    Route::get('configuracoes', [PomodoroSettingController::class, 'edit'])->name('pomodoro-settings.edit');
    Route::put('configuracoes', [PomodoroSettingController::class, 'update'])->name('pomodoro-settings.update');
});
