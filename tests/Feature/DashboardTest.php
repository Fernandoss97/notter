<?php

use App\Models\StudyPlan;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the evolution rolls tasks and questions up per subject, with totals', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $database = Subject::factory()->for($plan)->create(['name' => 'Banco de Dados']);
    $portuguese = Subject::factory()->for($plan)->create(['name' => 'Português']);

    Topic::factory()->for($database)->completed()->create([
        'exercises_total' => 30,
        'exercises_correct' => 24,
    ]);
    Topic::factory()->for($database)->create([
        'exercises_total' => 10,
        'exercises_correct' => 6,
    ]);
    Topic::factory()->for($portuguese)->create([
        'exercises_total' => 10,
        'exercises_correct' => 4,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('evolution.rows.0.name', 'Banco de Dados')
            ->where('evolution.rows.0.tasksDone', 1)
            ->where('evolution.rows.0.tasksTotal', 2)
            ->where('evolution.rows.0.progress', 50)
            ->where('evolution.rows.0.exercises', 40)
            ->where('evolution.rows.0.correct', 30)
            ->where('evolution.rows.0.accuracy', 75)
            ->where('evolution.rows.1.name', 'Português')
            ->where('evolution.rows.1.accuracy', 40)
            ->where('evolution.totals.tasksDone', 1)
            ->where('evolution.totals.tasksTotal', 3)
            ->where('evolution.totals.exercises', 50)
            ->where('evolution.totals.correct', 34)
            ->where('evolution.totals.accuracy', 68));
});

test('a subject without questions has no accuracy, not zero', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    Topic::factory()->for(Subject::factory()->for($plan))->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('evolution.rows.0.exercises', 0)
            ->where('evolution.rows.0.accuracy', null)
            ->where('evolution.totals.accuracy', null));
});

test('the evolution is empty without an active plan', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('evolution', null));
});
