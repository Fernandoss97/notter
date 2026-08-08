<?php

use App\Models\Enums\ActivityType;
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
            ->where('subjects', [])
            ->where('recentSessions', []));
});

test('the dashboard reports topics done and subject progress', function () {
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
            ->where('stats.topicsTotal', 2)
            ->where('subjects.0.name', 'Álgebra Linear')
            ->where('subjects.0.progress', 50));
});

test('the dashboard sums completed session minutes for today and this week', function () {
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
        ->assertInertia(fn ($page) => $page
            ->where('stats.todayMinutes', 50)
            ->where('stats.weekMinutes', 50));
});

test('a running session contributes its live elapsed time to today minutes', function () {
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
        ->assertInertia(fn ($page) => $page->where('stats.todayMinutes', 10));
});

test('the dashboard lists the five most recent completed sessions', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create(['name' => 'Álgebra Linear']);

    StudySession::factory()->for($user)->for($plan)->for($subject)->completed()->create([
        'accumulated_seconds' => 3000,
        'activity_type' => ActivityType::Exercises,
        'exercises_total' => 20,
        'exercises_correct' => 16,
        'ended_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('recentSessions.0.subject', 'Álgebra Linear')
            ->where('recentSessions.0.durationMinutes', 50)
            ->where('recentSessions.0.activityType', 'exercises')
            ->where('recentSessions.0.accuracy', 80));
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
