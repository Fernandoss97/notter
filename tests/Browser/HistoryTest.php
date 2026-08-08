<?php

use App\Models\Enums\ActivityType;
use App\Models\StudyPlan;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\User;

test('the history screen loads and filters sessions', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create(['name' => 'Álgebra Linear']);

    StudySession::factory()->for($user)->for($plan)->for($subject)->completed()->create([
        'activity_type' => ActivityType::Reading,
        'ended_at' => now(),
    ]);
    StudySession::factory()->for($user)->for($plan)->for($subject)->completed()->create([
        'activity_type' => ActivityType::Exercises,
        'ended_at' => now()->subDays(20),
    ]);

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/foco')
        ->assertNoJavaScriptErrors();

    $page->hover('[data-slot="sidebar"]')
        ->click('Histórico')
        ->assertPathIs('/historico')
        ->assertNoJavaScriptErrors();

    $page->assertSee('1 de 1')
        ->click('30 dias')
        ->assertSee('2 de 2')
        ->assertNoJavaScriptErrors();

    // "Leitura" também aparece na coluna Tipo da tabela de sessões, então um
    // clique por texto puro seria ambíguo — mira o item do SegmentedControl
    // explicitamente (ver histórico de travamentos com clique ambíguo em
    // tests/Browser/FocusSessionTest.php).
    $page->click('Todos os tipos')
        ->click('[data-slot="segmented-control-item"]:text("Leitura")')
        ->assertSee('1 de 1')
        ->assertNoJavaScriptErrors();
});
