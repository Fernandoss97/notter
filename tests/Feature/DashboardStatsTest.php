<?php

use App\Models\StudyPlan;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;

test('the dashboard renders an empty state without an active plan', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('plan', null)
            ->has('recentDays', 7));
});

test('the dashboard reports topics done across the plan', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create(['name' => 'Concurso XYZ']);
    $subject = Subject::factory()->for($plan)->create(['name' => 'Álgebra Linear']);
    Topic::factory()->for($subject)->completed()->create();
    Topic::factory()->for($subject)->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('plan.name', 'Concurso XYZ')
            ->where('stats.topicsDone', 1)
            ->where('stats.topicsTotal', 2));
});

test('the dashboard sums completed session minutes for this week', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();

    StudySession::factory()->for($user)->for($plan)->completed()->create([
        'accumulated_seconds' => 3000,
        'ended_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('stats.weekMinutes', 50));
});

test('a running session contributes its live elapsed time to the week', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();

    StudySession::factory()->for($user)->for($plan)->create([
        'accumulated_seconds' => 0,
        'resumed_at' => now(),
        'started_at' => now(),
    ]);

    $this->travel(10)->minutes();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('stats.weekMinutes', 10));
});

test('the dashboard no longer repeats the plan and the history', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();
    StudySession::factory()->for($user)->for($plan)->for($subject)->completed()->create([
        'ended_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->missing('subjects')
            ->missing('recentSessions'));
});

test('the last seven days come oldest first, with the empty ones kept', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();

    StudySession::factory()->for($user)->for($plan)->completed()->create([
        'accumulated_seconds' => 1800,
        'ended_at' => today()->subDays(6),
    ]);
    StudySession::factory()->for($user)->for($plan)->completed()->create([
        'accumulated_seconds' => 3600,
        'ended_at' => today(),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->has('recentDays', 7)
            ->where('recentDays.0.minutes', 30)
            ->where('recentDays.0.date', today()->subDays(6)->format('d/m'))
            ->where('recentDays.3.minutes', 0)
            ->where('recentDays.6.minutes', 60)
            ->where('recentDays.6.date', today()->format('d/m')));
});

test('the last seven days leave out what happened before the window', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();

    StudySession::factory()->for($user)->for($plan)->completed()->create([
        'accumulated_seconds' => 3600,
        'ended_at' => today()->subDays(8),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('recentDays.0.minutes', 0)
            ->where('recentDays.6.minutes', 0));
});

test('the last seven days follow the break preference', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    StudySession::factory()->for($user)->for($plan)->completed()->create([
        'accumulated_seconds' => 1800,
        'break_seconds' => 600,
        'ended_at' => today(),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('recentDays.6.minutes', 30));

    $user->pomodoroSetting()->firstOrCreate([])
        ->update(['exclude_breaks_from_study_time' => false]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('recentDays.6.minutes', 40));
});

test('the current streak counts consecutive days ending today', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();

    StudySession::factory()->for($user)->for($plan)->completed()->create(['ended_at' => today()]);
    StudySession::factory()->for($user)->for($plan)->completed()->create(['ended_at' => today()->subDay()]);
    StudySession::factory()->for($user)->for($plan)->completed()->create(['ended_at' => today()->subDays(2)]);
    StudySession::factory()->for($user)->for($plan)->completed()->create(['ended_at' => today()->subDays(10)]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('stats.currentStreak', 3)
            ->where('stats.longestStreak', 3));
});

test('the streak is still counted through yesterday when today has no session yet', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();

    StudySession::factory()->for($user)->for($plan)->completed()->create(['ended_at' => today()->subDay()]);
    StudySession::factory()->for($user)->for($plan)->completed()->create(['ended_at' => today()->subDays(2)]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('stats.currentStreak', 2));
});
