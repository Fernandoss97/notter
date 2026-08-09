<?php

use App\Models\PomodoroSetting;
use App\Models\Review;
use App\Models\StudyPlan;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;

/**
 * Os controles do cronômetro são mirados dentro de `[data-slot="focus-timer"]`.
 * A barra de sessão persistente também tem um botão "Pausar" e continua no DOM
 * na tela de foco — colapsada e `inert`, mas um clique por texto puro resolve
 * para ela primeiro e espera para sempre por um elemento invisível.
 */
function timerButton(string $label): string
{
    return sprintf('[data-slot="focus-timer"] button:text-is("%s")', $label);
}

test('a user can start, pause and resume a focus session', function () {
    $user = User::factory()->create();
    StudyPlan::factory()->for($user)->active()->create();

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/foco')
        ->assertNoJavaScriptErrors();

    $page->assertSee('Iniciar sessão')
        ->click('Iniciar sessão de foco')
        ->assertSee('Em foco')
        ->assertNoJavaScriptErrors();

    $page->click(timerButton('Pausar'))
        ->assertSee('Pausado')
        ->assertNoJavaScriptErrors();

    $page->click(timerButton('Retomar'))
        ->assertSee('Em foco')
        ->assertNoJavaScriptErrors();
});

test('the focus timer starts counting down from the configured cycle', function () {
    $user = User::factory()->create();
    // Um ciclo de 1 minuto para o teste não esperar 50.
    PomodoroSetting::factory()->for($user)->create([
        'focus_minutes' => 1,
        'short_break_minutes' => 5,
        'auto_start_break' => true,
    ]);
    $plan = StudyPlan::factory()->for($user)->active()->create();
    Subject::factory()->for($plan)->create(['name' => 'Álgebra Linear']);

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/foco')
        ->assertNoJavaScriptErrors();

    $page->click('Iniciar sessão de foco')
        // O cronômetro é regressivo: começa na duração do ciclo.
        ->assertSee('01:00')
        ->assertNoJavaScriptErrors();
});

test('a user can complete a due review from the focus screen', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create(['name' => 'Direito Constitucional']);
    $topic = Topic::factory()->for($subject)->completed()->create(['name' => 'Princípios fundamentais']);
    Review::factory()->for($topic)->create([
        'sequence' => 1,
        'due_on' => today()->subDays(2),
    ]);

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/foco')
        ->assertNoJavaScriptErrors();

    $page->assertSee('Revisões de hoje')
        ->assertSee('Princípios fundamentais')
        ->assertSee('1 atrasada')
        ->click('Feito')
        ->assertSee('Nenhuma revisão para hoje')
        ->assertNoJavaScriptErrors();

    expect($topic->reviews()->whereNotNull('completed_at')->count())->toBe(1)
        ->and($topic->reviews()->where('sequence', 2)->exists())->toBeTrue();
});

test('the zen mode hides everything but the timer', function () {
    $user = User::factory()->create();
    StudyPlan::factory()->for($user)->active()->create();

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/foco')
        ->assertNoJavaScriptErrors();

    // Os parênteses de "Sair do modo zen (Esc)" quebram o parser de
    // seletores CSS do Playwright — daí o :has-text() explícito.
    $leaveZen = 'button:has-text("Sair do modo zen")';

    $page->click('Iniciar sessão de foco')
        ->assertSee('Notas da sessão')
        ->click('Modo zen')
        ->assertSee('Sair do modo zen')
        ->assertDontSee('Notas da sessão')
        ->assertNoJavaScriptErrors();

    // A cor tema é escolhida de dentro do próprio zen.
    $page->click('button[aria-label="Índigo"]')
        ->assertAttribute('div[data-zen-theme]', 'data-zen-theme', 'indigo')
        ->assertNoJavaScriptErrors();

    $page->click($leaveZen)
        ->assertSee('Notas da sessão')
        ->assertNoJavaScriptErrors();
});
