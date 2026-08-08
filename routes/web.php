<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * A raiz não tem conteúdo próprio: um app de estudos pessoal não tem
 * landing page para quem já entrou nem para quem ainda não entrou. Ela só
 * encaminha para a tela de foco ou para o login.
 */
Route::get('/', fn (Request $request) => $request->user()
    ? to_route('focus.show')
    : to_route('login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/study.php';
