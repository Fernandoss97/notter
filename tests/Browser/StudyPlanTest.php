<?php

use App\Models\StudyPlan;
use App\Models\Subject;
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

test('a user with a plan can create a second one and switch back', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create(['name' => 'Concurso XYZ']);
    Subject::factory()->for($plan)->create(['name' => 'Álgebra Linear']);

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/foco')
        ->assertNoJavaScriptErrors();

    $page->hover('[data-slot="sidebar"]')
        ->click('Plano de estudos')
        ->assertPathIs('/plano')
        ->assertSee('Álgebra Linear')
        ->assertNoJavaScriptErrors();

    $page->click('Novo plano')
        ->fill('new_plan_name', 'Concurso ABC')
        ->click('Criar plano')
        ->assertSee('Concurso ABC')
        ->assertDontSee('Álgebra Linear')
        ->assertNoJavaScriptErrors();

    $page->click('[data-slot="segmented-control"] button:text-is("Concurso XYZ")')
        ->assertSee('Álgebra Linear')
        ->assertNoJavaScriptErrors();
});
