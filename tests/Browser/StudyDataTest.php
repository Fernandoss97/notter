<?php

use App\Models\StudyPlan;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;

test('a user can wipe the study data and keep the account', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create(['name' => 'Concurso XYZ']);
    Topic::factory()->for(Subject::factory()->for($plan)->create(['name' => 'Banco de Dados']))->create();

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/foco')
        ->assertNoJavaScriptErrors();

    // "Configurações" na sidebar é a porta de entrada.
    $page->hover('[data-slot="sidebar"]')
        ->click('Configurações')
        ->assertPathIs('/settings/profile')
        ->assertSee('Apagar dados de estudo')
        ->assertNoJavaScriptErrors();

    $page->click('[data-test="delete-study-data-button"]')
        // O campo vai por seletor CSS: dois diálogos nesta tela têm um
        // input chamado `password`, e o `fill` por nome não escolheria.
        ->fill('#study_data_password', 'password')
        ->click('[data-test="confirm-delete-study-data-button"]')
        ->assertSee('Dados de estudo apagados')
        ->assertNoJavaScriptErrors();

    // A conta sobrevive ao expurgo — que a tela de foco continue abrindo
    // depois disso é o que o teste de feature garante.
    $this->assertAuthenticatedAs($user);

    expect($user->studyPlans()->count())->toBe(0)
        ->and(Topic::count())->toBe(0);
});
