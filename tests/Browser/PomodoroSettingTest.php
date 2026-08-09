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

    // O item do usuário na sidebar é a porta de entrada das configurações; a
    // tela de pomodoro fica no menu lateral de lá.
    $page->hover('[data-slot="sidebar"]')
        ->click($user->name)
        ->assertPathIs('/settings/profile')
        ->click('Pomodoro')
        ->assertPathIs('/configuracoes')
        ->assertNoJavaScriptErrors();

    $page->fill('focus_minutes', '25')
        ->click('Salvar alterações')
        ->assertNoJavaScriptErrors();

    expect($user->pomodoroSetting()->sole()->focus_minutes)->toBe(25);
});
