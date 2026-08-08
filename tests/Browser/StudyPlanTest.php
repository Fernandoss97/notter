<?php

use App\Models\User;

test('a user can create a plan, add a subject and topic, and complete it', function () {
    $user = User::factory()->create();

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/foco')
        ->assertNoJavaScriptErrors();

    $page->hover('[data-slot="sidebar"]')
        ->click('Plano de estudos')
        ->assertPathIs('/plano')
        ->assertNoJavaScriptErrors();

    $page->assertSee('Criar plano de estudos')
        ->fill('plan_name', 'Concurso XYZ')
        ->click('Criar plano')
        ->assertSee('Concurso XYZ')
        ->assertNoJavaScriptErrors();

    $page->fill('subject_name', 'Álgebra Linear')
        ->click('Adicionar')
        ->assertSee('Álgebra Linear')
        ->assertNoJavaScriptErrors();

    $page->fill('[placeholder="Novo tópico"]', 'Espaços vetoriais')
        ->click('Adicionar tópico')
        ->assertSee('Espaços vetoriais')
        ->assertNoJavaScriptErrors();

    $page->click('[aria-label="Marcar Espaços vetoriais como concluído"]')
        ->assertSee('Em dia')
        ->assertNoJavaScriptErrors();
});
