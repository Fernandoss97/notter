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
        // O rodapé traz o atalho das configurações e a saída, sem menu
        // suspenso — e nomeia o destino, não quem está logado.
        ->assertSee('Configurações')
        ->assertDontSee($user->name)
        ->assertSee('Sair')
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
