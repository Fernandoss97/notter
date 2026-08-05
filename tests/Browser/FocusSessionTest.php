<?php

use App\Models\StudyPlan;
use App\Models\User;

test('a user can start, pause and resume a focus session', function () {
    $user = User::factory()->create();
    StudyPlan::factory()->for($user)->active()->create();

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/dashboard')
        ->assertNoJavaScriptErrors();

    // Navegar clicando no link (não chamar visit() de novo): cada visit()
    // abre um browser context novo, sem os cookies de sessão do login.
    $page->hover('[data-slot="sidebar"]')
        ->click('Foco')
        ->assertPathIs('/foco')
        ->assertNoJavaScriptErrors();

    $page->assertSee('Iniciar sessão')
        ->click('Iniciar sessão de foco')
        ->assertSee('Em foco')
        ->assertNoJavaScriptErrors();

    $page->click('Pausar')
        ->assertSee('Pausado')
        ->assertNoJavaScriptErrors();

    $page->click('Retomar')
        ->assertSee('Em foco')
        ->assertNoJavaScriptErrors();
});
