<?php

use App\Models\StudyPlan;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;

test('the progress screen shows the week, the streak and the last seven days', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create([
        'name' => 'Concurso XYZ',
        'weekly_goal_minutes' => 1200,
    ]);
    $subject = Subject::factory()->for($plan)->create(['name' => 'Álgebra Linear']);
    Topic::factory()->for($subject)->completed()->create();
    Topic::factory()->for($subject)->create();
    StudySession::factory()->for($user)->for($plan)->for($subject)->completed()->create([
        'accumulated_seconds' => 3000,
        'ended_at' => now(),
    ]);

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/foco')
        ->assertNoJavaScriptErrors();

    $page->hover('[data-slot="sidebar"]')
        ->click('Progresso')
        ->assertPathIs('/dashboard')
        ->assertNoJavaScriptErrors();

    $page->assertSee('Concurso XYZ')
        ->assertSee('Semana')
        ->assertSee('50min')
        ->assertSee('1/2')
        ->assertSee('Sequência')
        ->assertSee('Últimos 7 dias')
        ->assertNoJavaScriptErrors();

    // Sessão por sessão continua sendo assunto do histórico.
    $page->assertDontSee('Histórico de sessões');

    // Disciplina por disciplina voltou — não como repetição do plano, mas
    // pelo acerto em questões, que não aparece em nenhuma outra tela.
    $page->assertSee('Evolução por disciplina')
        ->assertSee('Álgebra Linear')
        ->assertSee('Desempenho')
        ->assertNoJavaScriptErrors();
});

test('the progress screen switches between plans', function () {
    $user = User::factory()->create();
    $xyz = StudyPlan::factory()->for($user)->active()->create(['name' => 'Concurso XYZ']);
    $abc = StudyPlan::factory()->for($user)->create(['name' => 'Concurso ABC']);

    Topic::factory()->for(Subject::factory()->for($xyz)->create(['name' => 'Álgebra Linear']))->create();
    Topic::factory()->for(Subject::factory()->for($abc)->create(['name' => 'Direito Penal']))->create();

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/foco')
        ->assertNoJavaScriptErrors();

    $page->hover('[data-slot="sidebar"]')
        ->click('Progresso')
        ->assertPathIs('/dashboard')
        ->assertSee('Álgebra Linear')
        ->assertNoJavaScriptErrors();

    $page->click('[data-slot="segmented-control"] button:text-is("Concurso ABC")')
        // Trocar de plano não pode tirar o usuário da tela em que ele está.
        ->assertPathIs('/dashboard')
        ->assertSee('Direito Penal')
        ->assertDontSee('Álgebra Linear')
        ->assertNoJavaScriptErrors();
});

test('the progress screen counts down to the exam and compares the paces', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create([
        'exam_date' => today()->addDays(70)->toDateString(),
    ]);
    $subject = Subject::factory()->for($plan)->create();
    Topic::factory()->for($subject)->count(30)->create();

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/foco')
        ->assertNoJavaScriptErrors();

    $page->hover('[data-slot="sidebar"]')
        ->click('Progresso')
        ->assertPathIs('/dashboard')
        ->assertSee('Prazo e ritmo')
        ->assertSee('70 dias')
        ->assertSee('30 de 30')
        // 30 tarefas em 10 semanas, contra um ritmo parado.
        ->assertSee('3 tarefas/semana')
        ->assertSee('Abaixo do ritmo')
        ->assertNoJavaScriptErrors();
});

test('a plan without an exam date asks for one instead of inventing a pace', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create(['exam_date' => null]);
    Topic::factory()->for(Subject::factory()->for($plan))->create();

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/foco')
        ->assertNoJavaScriptErrors();

    $page->hover('[data-slot="sidebar"]')
        ->click('Progresso')
        ->assertSee('Sem data de prova')
        ->assertDontSee('Ritmo necessário')
        ->assertNoJavaScriptErrors();
});

test('the evolution reports the accuracy of each subject and the totals', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $database = Subject::factory()->for($plan)->create(['name' => 'Banco de Dados']);
    $portuguese = Subject::factory()->for($plan)->create(['name' => 'Português']);

    Topic::factory()->for($database)->completed()->create([
        'exercises_total' => 40,
        'exercises_correct' => 30,
    ]);
    Topic::factory()->for($portuguese)->create([
        'exercises_total' => 10,
        'exercises_correct' => 4,
    ]);

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/foco')
        ->assertNoJavaScriptErrors();

    $page->hover('[data-slot="sidebar"]')
        ->click('Progresso')
        ->assertPathIs('/dashboard')
        ->assertSee('75%')
        ->assertSee('40%')
        ->assertSee('Totais')
        ->assertSee('68%')
        ->assertNoJavaScriptErrors();
});
