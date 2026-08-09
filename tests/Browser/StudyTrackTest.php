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

    // E o clique errado tem volta.
    $page->click('[aria-label="Desmarcar Estudo da Aula 00 como concluída"]')
        ->assertSee('0 de 1 tarefas')
        ->assertDontSee('Em dia')
        ->assertNoJavaScriptErrors();
});

test('the track switches between plans', function () {
    $user = User::factory()->create();
    $xyz = StudyPlan::factory()->for($user)->active()->create(['name' => 'Concurso XYZ']);
    $abc = StudyPlan::factory()->for($user)->create(['name' => 'Concurso ABC']);

    Topic::factory()->for(Subject::factory()->for($xyz))->create(['name' => 'Aula de XYZ']);
    Topic::factory()->for(Subject::factory()->for($abc))->create(['name' => 'Aula de ABC']);

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/foco')
        ->assertNoJavaScriptErrors();

    $page->hover('[data-slot="sidebar"]')
        ->click('Trilha')
        ->assertPathIs('/trilha')
        ->assertSee('Aula de XYZ')
        ->assertNoJavaScriptErrors();

    $page->click('[data-slot="segmented-control"] button:text-is("Concurso ABC")')
        // Trocar de plano não pode tirar o usuário da tela em que ele está.
        ->assertPathIs('/trilha')
        ->assertSee('Aula de ABC')
        ->assertDontSee('Aula de XYZ')
        ->assertNoJavaScriptErrors();
});

test('a user can record the questions of a task by hand', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create(['name' => 'Banco de Dados']);
    Topic::factory()->for($subject)->inQueue(0, 0)->create(['name' => 'Aula 00 de BD']);

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/foco')
        ->assertNoJavaScriptErrors();

    $page->hover('[data-slot="sidebar"]')
        ->click('Trilha')
        ->assertPathIs('/trilha')
        ->assertNoJavaScriptErrors();

    $page->click('[aria-label="Registrar questões de Aula 00 de BD"]')
        ->fill('exercises_total', '20')
        ->fill('exercises_correct', '15')
        ->click('[data-slot="dialog-content"] button:text-is("Salvar")')
        ->assertSee('15/20 questões')
        ->assertSee('75%')
        ->assertNoJavaScriptErrors();
});

test('a user can import a track pasted from the spreadsheet', function () {
    $user = User::factory()->create();
    StudyPlan::factory()->for($user)->active()->create();

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/foco')
        ->assertNoJavaScriptErrors();

    $page->hover('[data-slot="sidebar"]')
        ->click('Trilha')
        ->assertPathIs('/trilha')
        ->assertNoJavaScriptErrors();

    $page->click('Importar trilha')
        ->fill('content', implode("\n", [
            "0\t1\tBanco de Dados\tEstudo da Aula 00",
            "0\t2\tPortuguês\tEstudo da Aula 00",
            "1\t3\tBanco de Dados\tEstudo da Aula 01",
        ]))
        ->click('[data-slot="dialog-content"] button:text-is("Importar")')
        ->assertSee('3 tarefas importadas')
        ->assertSee('2 disciplinas criadas')
        ->assertNoJavaScriptErrors();

    $page->assertSee('Ciclo 1')
        ->assertSee('Ciclo 2')
        ->assertSee('Estudo da Aula 00')
        ->assertSee('Estudo da Aula 01')
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
