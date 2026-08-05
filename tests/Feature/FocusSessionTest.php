<?php

use App\Models\Enums\ActivityType;
use App\Models\Enums\FocusSessionState;
use App\Models\PomodoroSetting;
use App\Models\StudyPlan;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;

test('guests are redirected away from the focus screen', function () {
    $this->get('/foco')->assertRedirect('/login');
});

test('the focus screen renders the active plan subjects and topics', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create(['name' => 'Álgebra Linear']);
    Topic::factory()->for($subject)->create(['name' => 'Espaços vetoriais']);

    $this->actingAs($user)
        ->get('/foco')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('focus/show')
            ->where('plan.name', $plan->name)
            ->where('plan.subjects.0.name', 'Álgebra Linear')
            ->where('plan.subjects.0.topics.0.name', 'Espaços vetoriais')
            ->where('session', null));
});

test('the focus screen renders without an active plan', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/foco')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('plan', null));
});

test('starting a session snapshots the pomodoro focus duration and persists it', function () {
    $user = User::factory()->create();
    PomodoroSetting::factory()->for($user)->create(['focus_minutes' => 30]);
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();

    $this->actingAs($user)
        ->post('/foco/start', ['subject_id' => $subject->id])
        ->assertRedirect(route('focus.show'));

    $session = $user->studySessions()->sole();
    expect($session->state)->toBe(FocusSessionState::Running)
        ->and($session->planned_seconds)->toBe(30 * 60)
        ->and($session->subject_id)->toBe($subject->id);
});

test('starting a session rejects a topic that does not belong to the chosen subject', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();
    $otherSubject = Subject::factory()->for($plan)->create();
    $foreignTopic = Topic::factory()->for($otherSubject)->create();

    $this->actingAs($user)
        ->post('/foco/start', ['subject_id' => $subject->id, 'topic_id' => $foreignTopic->id])
        ->assertInvalid(['topic_id']);
});

test('starting a session without an active plan flashes an error and does not create a session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/foco/start');

    expect($user->studySessions()->count())->toBe(0);
});

test('a user cannot start a second session while one is active', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $this->actingAs($user)->post('/foco/start');

    $this->post('/foco/start');

    expect($user->studySessions()->count())->toBe(1);
});

test('pausing the active session freezes elapsed time', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $session = StudySession::factory()->for($user)->create([
        'state' => FocusSessionState::Running,
        'accumulated_seconds' => 0,
        'resumed_at' => now(),
    ]);

    $this->travel(30)->seconds();

    $this->actingAs($user)
        ->post('/foco/pause')
        ->assertRedirect(route('focus.show'));

    expect($session->refresh()->state)->toBe(FocusSessionState::Paused)
        ->and($session->accumulated_seconds)->toBe(30);
});

test('resuming the paused session opens a new live segment', function () {
    $user = User::factory()->create();
    $session = StudySession::factory()->for($user)->paused()->create();

    $this->actingAs($user)
        ->post('/foco/resume')
        ->assertRedirect(route('focus.show'));

    expect($session->refresh()->state)->toBe(FocusSessionState::Running);
});

test('completing the active session registers subject, topic and accuracy', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();
    $topic = Topic::factory()->for($subject)->create();
    $session = StudySession::factory()->for($user)->create([
        'state' => FocusSessionState::Running,
        'subject_id' => null,
        'topic_id' => null,
    ]);

    $this->actingAs($user)
        ->post('/foco/complete', [
            'subject_id' => $subject->id,
            'topic_id' => $topic->id,
            'activity_type' => ActivityType::Exercises->value,
            'exercises_total' => 20,
            'exercises_correct' => 16,
        ])
        ->assertRedirect(route('focus.show'));

    $session->refresh();
    expect($session->state)->toBe(FocusSessionState::Completed)
        ->and($session->subject_id)->toBe($subject->id)
        ->and($session->topic_id)->toBe($topic->id)
        ->and($session->accuracyPercent())->toBe(80);
});

test('completing a session rejects exercises_correct greater than exercises_total', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();
    StudySession::factory()->for($user)->create(['state' => FocusSessionState::Running]);

    $this->actingAs($user)
        ->post('/foco/complete', [
            'subject_id' => $subject->id,
            'activity_type' => ActivityType::Exercises->value,
            'exercises_total' => 10,
            'exercises_correct' => 15,
        ])
        ->assertInvalid(['exercises_correct']);
});

test('transitioning without an active session flashes an error instead of failing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/foco/pause')
        ->assertRedirect(route('focus.show'));
});
