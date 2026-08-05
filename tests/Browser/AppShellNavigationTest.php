<?php

use App\Models\User;

test('the sidebar shows the product navigation and marks dashboard as active', function () {
    $user = User::factory()->create();

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/dashboard')
        ->assertNoJavaScriptErrors();

    $page->hover('[data-slot="sidebar"]')
        ->assertSee('Dashboard')
        ->assertSee('Plano de estudos')
        ->assertSee('Foco')
        ->assertSee('Histórico')
        ->assertSee('Configurações')
        ->assertNoJavaScriptErrors();

    $page->assertAttribute(
        '[data-sidebar="menu-button"][data-active="true"]',
        'data-active',
        'true',
    )->assertSeeIn(
        '[data-sidebar="menu-button"][data-active="true"]',
        'Dashboard',
    );
});
