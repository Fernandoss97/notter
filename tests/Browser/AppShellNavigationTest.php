<?php

use App\Models\User;

test('the sidebar shows the product navigation and marks focus as active', function () {
    $user = User::factory()->create();

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/foco')
        ->assertNoJavaScriptErrors();

    $page->hover('[data-slot="sidebar"]')
        ->assertSee('Foco')
        ->assertSee('Progresso')
        ->assertSee('Plano de estudos')
        ->assertSee('Histórico')
        ->assertSee('Configurações')
        ->assertNoJavaScriptErrors();

    // Foco é a tela principal: o login cai nela e é ela que fica marcada.
    $page->assertAttribute(
        '[data-sidebar="menu-button"][data-active="true"]',
        'data-active',
        'true',
    )->assertSeeIn(
        '[data-sidebar="menu-button"][data-active="true"]',
        'Foco',
    );
});
