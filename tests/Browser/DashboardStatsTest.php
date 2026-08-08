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

    // O que virou duplicata saiu daqui e ficou onde já morava.
    $page->assertDontSee('Álgebra Linear')
        ->assertDontSee('Histórico de sessões');
});
