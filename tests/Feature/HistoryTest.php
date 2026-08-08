<?php

use App\Models\Enums\ActivityType;
use App\Models\StudyPlan;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\User;

test('guests are redirected away from the history screen', function () {
    $this->get('/historico')->assertRedirect('/login');
});

test('the history screen defaults to the last 7 days', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();

    StudySession::factory()->for($user)->for($plan)->completed()->create(['ended_at' => now()->subDays(2)]);
    StudySession::factory()->for($user)->for($plan)->completed()->create(['ended_at' => now()->subDays(10)]);

    $this->actingAs($user)
        ->get('/historico')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('history/index')
            ->where('filters.period', '7')
            ->where('filters.type', 'all')
            ->where('total', 1));
});

test('the period filter widens the window', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();

    StudySession::factory()->for($user)->for($plan)->completed()->create(['ended_at' => now()->subDays(20)]);

    $this->actingAs($user)
        ->get('/historico?period=30')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('total', 1));

    $this->actingAs($user)
        ->get('/historico?period=7')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('total', 0));
});

test('the type filter narrows to a single activity type', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();

    StudySession::factory()->for($user)->for($plan)->completed()->create([
        'activity_type' => ActivityType::Reading,
        'ended_at' => now(),
    ]);
    StudySession::factory()->for($user)->for($plan)->completed()->create([
        'activity_type' => ActivityType::Exercises,
        'ended_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/historico?type=reading')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('total', 1));
});

test('an invalid period is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/historico?period=invalid')
        ->assertInvalid(['period']);
});

test('rows include subject, duration and accuracy', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create(['name' => 'Álgebra Linear']);

    StudySession::factory()->for($user)->for($plan)->for($subject)->completed()->create([
        'accumulated_seconds' => 3000,
        'exercises_total' => 20,
        'exercises_correct' => 16,
        'ended_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/historico')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('sessions.0.subject', 'Álgebra Linear')
            ->where('sessions.0.durationMinutes', 50)
            ->where('sessions.0.accuracy', 80));
});

test('sessions belonging to another user are never shown', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherPlan = StudyPlan::factory()->for($otherUser)->active()->create();
    StudySession::factory()->for($otherUser)->for($otherPlan)->completed()->create(['ended_at' => now()]);

    $this->actingAs($user)
        ->get('/historico')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('total', 0));
});

test('the history duration follows the break preference', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();
    StudySession::factory()->for($user)->for($plan)->for($subject)->completed()->create([
        'accumulated_seconds' => 3000,
        'break_seconds' => 600,
        'ended_at' => now(),
    ]);

    // Padrão: só o tempo de foco.
    $this->actingAs($user)
        ->get('/historico')
        ->assertInertia(fn ($page) => $page->where('sessions.0.durationMinutes', 50));

    $user->pomodoroSetting()->firstOrCreate([])->update(['exclude_breaks_from_study_time' => false]);

    $this->actingAs($user)
        ->get('/historico')
        ->assertInertia(fn ($page) => $page->where('sessions.0.durationMinutes', 60));
});
