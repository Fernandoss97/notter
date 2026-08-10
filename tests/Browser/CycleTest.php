<?php

use App\Models\CycleSlot;
use App\Models\StudyPlan;
use App\Models\Subject;
use App\Models\User;

test('a user builds a rotation and the focus screen announces whose turn it is', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create(['name' => 'Concurso XYZ']);
    Subject::factory()->for($plan)->create(['name' => 'Direito Constitucional']);
    Subject::factory()->for($plan)->create(['name' => 'Português']);

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/foco')
        ->assertNoJavaScriptErrors();

    $page->hover('[data-slot="sidebar"]')
        ->click('Ciclo')
        ->assertPathIs('/ciclo')
        ->assertSee('Nenhuma disciplina no rodízio ainda')
        ->assertNoJavaScriptErrors();

    $page->click('#cycle_subject')
        ->click('[role="option"]:has-text("Direito Constitucional")')
        ->click('button:text-is("Adicionar")')
        ->assertSee('Direito Constitucional')
        ->assertSee('É a vez')
        ->assertNoJavaScriptErrors();

    $page->click('#cycle_subject')
        ->click('[role="option"]:has-text("Português")')
        ->click('button:text-is("Adicionar")')
        ->assertSee('Volta 1')
        ->assertSee('0 de 2 blocos')
        ->assertNoJavaScriptErrors();

    // A vez do ciclo chega à tela de foco, e é ela que decide a disciplina.
    $page->hover('[data-slot="sidebar"]')
        ->click('Foco')
        ->assertPathIs('/foco')
        ->assertSee('A vez do ciclo')
        ->assertSee('volta 1')
        ->assertNoJavaScriptErrors();
});

test('a block picks up where it stopped after the page is left and reopened', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create(['name' => 'Concurso XYZ']);
    $subject = Subject::factory()->for($plan)->create(['name' => 'Direito Constitucional']);
    CycleSlot::factory()->create([
        'study_plan_id' => $plan->id,
        'subject_id' => $subject->id,
        'position' => 0,
        'block_minutes' => 60,
    ]);

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/foco')
        ->assertNoJavaScriptErrors();

    $page->click('button:has-text("Iniciar sessão de foco")')
        ->assertSee('Direito Constitucional')
        ->assertNoJavaScriptErrors();

    // Pausar congela o tempo no bloco. O relógio da sessão vive no
    // servidor, então sair da página não pode custar nada ao bloco.
    $page->click('[data-slot="focus-timer"] button:text-is("Pausar")')
        ->assertNoJavaScriptErrors();

    $page->navigate('/ciclo')
        ->assertPathIs('/ciclo')
        ->assertSee('É a vez')
        ->assertSee('de 1h 0min')
        ->assertNoJavaScriptErrors();

    // E a sessão continua de pé ao voltar: o bloco não foi encerrado por
    // ninguém ter saído da tela.
    $page->navigate('/foco')
        ->assertSee('A vez do ciclo')
        ->assertNoJavaScriptErrors();
});

test('a block can be called done ahead of the target and the rotation moves on', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create(['name' => 'Concurso XYZ']);

    foreach (['Direito Constitucional', 'Português'] as $position => $name) {
        CycleSlot::factory()->create([
            'study_plan_id' => $plan->id,
            'subject_id' => Subject::factory()->for($plan)->create(['name' => $name])->id,
            'position' => $position,
        ]);
    }

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertNoJavaScriptErrors();

    // Fechar um bloco convida para a próxima matéria — é o único momento
    // em que a intercalação fala com quem estuda.
    $page->navigate('/ciclo')
        ->click('button:text-is("Encerrar")')
        ->assertSee('Agora é Português')
        ->assertSee('1 de 2 blocos')
        ->assertNoJavaScriptErrors();

    // A vez passou para a próxima disciplina do rodízio.
    $page->assertSee('Concluído')
        ->assertSee('É a vez')
        ->assertNoJavaScriptErrors();
});
