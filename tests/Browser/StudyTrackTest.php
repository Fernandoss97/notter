<?php

use App\Models\StudyPlan;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;

test('a user can add a task to the track and complete it', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create(['name' => 'Concurso XYZ']);
    Subject::factory()->for($plan)->create(['name' => 'Banco de Dados']);

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/foco')
        ->assertNoJavaScriptErrors();

    $page->hover('[data-slot="sidebar"]')
        ->click('Trilha')
        ->assertPathIs('/trilha')
        ->assertSee('Nenhuma tarefa na trilha ainda')
        ->assertNoJavaScriptErrors();

    $page->click('#task_subject')
        ->click('[role="option"]:has-text("Banco de Dados")')
        ->fill('task_name', 'Estudo da Aula 00')
        // "Adicionar" também é o começo do título do painel: só o botão serve.
        ->click('button:text-is("Adicionar")')
        ->assertSee('Estudo da Aula 00')
        ->assertSee('Ciclo 1')
        ->assertNoJavaScriptErrors();

    $page->click('[aria-label="Marcar Estudo da Aula 00 como concluída"]')
        ->assertSee('Em dia')
        ->assertSee('1 de 1 tarefas')
        ->assertNoJavaScriptErrors();
});

test('the track lists the queue across subjects, cycle by cycle', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $database = Subject::factory()->for($plan)->create(['name' => 'Banco de Dados']);
    $logic = Subject::factory()->for($plan)->create(['name' => 'Raciocínio Lógico']);

    Topic::factory()->for($database)->inQueue(0, 0)->create(['name' => 'Aula 00 de BD']);
    Topic::factory()->for($logic)->inQueue(0, 1)->create(['name' => 'Estruturas Lógicas I']);
    Topic::factory()->for($database)->inQueue(1, 2)->create(['name' => 'Aula 01 de BD']);

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/foco')
        ->assertNoJavaScriptErrors();

    $page->hover('[data-slot="sidebar"]')
        ->click('Trilha')
        ->assertPathIs('/trilha')
        ->assertSee('Aula 00 de BD')
        ->assertSee('Estruturas Lógicas I')
        ->assertSee('Ciclo 1')
        ->assertSee('Ciclo 2')
        // O estado vazio continua montado (para animar a própria entrada),
        // então o que se verifica é que ele está desligado, não ausente.
        ->assertAttribute('[data-slot="track-empty"]', 'data-visible', 'false')
        ->assertNoJavaScriptErrors();
});
