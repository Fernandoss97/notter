<?php

use App\Models\User;

test('a user can change the focus duration and save', function () {
    $user = User::factory()->create();

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/foco')
        ->assertNoJavaScriptErrors();

    // "Configurações" na sidebar é a porta de entrada; a tela de pomodoro
    // fica no menu lateral de lá.
    $page->hover('[data-slot="sidebar"]')
        ->click('Configurações')
        ->assertPathIs('/settings/profile')
        ->click('Pomodoro')
        ->assertPathIs('/configuracoes')
        ->assertNoJavaScriptErrors();

    $page->fill('focus_minutes', '25')
        ->click('Salvar alterações')
        ->assertNoJavaScriptErrors();

    expect($user->pomodoroSetting()->sole()->focus_minutes)->toBe(25);
});

test('a user sets the daily goal that the focus screen will chase', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    // Por id: o campo divide a tela com outros números em minutos, e
    // `fill` por nome ambíguo espera para sempre em vez de falhar.
    $page = visit('/configuracoes');

    $page->fill('#daily_goal_minutes', '90')
        ->assertSee('10h 30min')
        ->click('Salvar alterações')
        ->assertNoJavaScriptErrors();

    expect($user->pomodoroSetting()->sole()->daily_goal_minutes)->toBe(90);
});
