<?php

use App\Models\Enums\ActivityType;
use App\Models\Enums\FocusPhase;
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

test('advancing the phase closes the cycle and opens the break', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    PomodoroSetting::factory()->for($user)->create([
        'focus_minutes' => 1,
        'short_break_minutes' => 10,
        'auto_start_break' => true,
    ]);
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $session = StudySession::factory()->for($user)->create([
        'planned_seconds' => 60,
        'resumed_at' => now(),
    ]);

    $this->travel(60)->seconds();

    $this->actingAs($user)
        ->post('/foco/advance')
        ->assertRedirect(route('focus.show'));

    $session->refresh();
    expect($session->phase)->toBe(FocusPhase::ShortBreak)
        ->and($session->completed_cycles)->toBe(1)
        ->and($session->planned_seconds)->toBe(10 * 60);
});

test('a second advance for the same expired cycle does not count twice', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    PomodoroSetting::factory()->for($user)->create(['short_break_minutes' => 10]);
    StudyPlan::factory()->for($user)->active()->create();
    $session = StudySession::factory()->for($user)->create([
        'planned_seconds' => 60,
        'resumed_at' => now(),
    ]);

    $this->travel(60)->seconds();

    $this->actingAs($user)->post('/foco/advance');
    $this->post('/foco/advance');

    expect($session->refresh()->completed_cycles)->toBe(1)
        ->and($session->phase)->toBe(FocusPhase::ShortBreak);
});

test('skipping a break returns to focus without waiting for it to end', function () {
    $user = User::factory()->create();
    PomodoroSetting::factory()->for($user)->create(['focus_minutes' => 50]);
    StudyPlan::factory()->for($user)->active()->create();
    $session = StudySession::factory()->for($user)->onBreak()->create([
        'phase_accumulated_seconds' => 10,
        'state' => FocusSessionState::Paused,
        'resumed_at' => null,
    ]);

    $this->actingAs($user)->post('/foco/advance', ['skip' => true]);

    expect($session->refresh()->phase)->toBe(FocusPhase::Focus)
        ->and($session->planned_seconds)->toBe(50 * 60);
});

test('the focus screen reports the phase clock, not the session total', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    StudyPlan::factory()->for($user)->active()->create();
    StudySession::factory()->for($user)->onBreak()->create([
        'accumulated_seconds' => 3000,
        'phase_accumulated_seconds' => 0,
        'planned_seconds' => 600,
        'resumed_at' => now(),
    ]);

    $this->travel(30)->seconds();

    $this->actingAs($user)
        ->get('/foco')
        ->assertInertia(fn ($page) => $page
            ->where('session.phase', 'short_break')
            ->where('session.elapsedSeconds', 30)
            ->where('session.focusSeconds', 3000)
            ->where('session.plannedSeconds', 600));
});

test('a session can be closed without registering anything', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();
    $session = StudySession::factory()->for($user)->create([
        'subject_id' => $subject->id,
        'activity_type' => ActivityType::Reading,
    ]);

    $this->actingAs($user)
        ->post('/foco/complete')
        ->assertRedirect(route('focus.show'));

    $session->refresh();
    expect($session->state)->toBe(FocusSessionState::Completed)
        ->and($session->subject_id)->toBe($subject->id)
        ->and($session->activity_type)->toBe(ActivityType::Reading);
});

test('notes are saved without a full Inertia round trip', function () {
    $user = User::factory()->create();
    StudyPlan::factory()->for($user)->active()->create();
    $session = StudySession::factory()->for($user)->create();

    $this->actingAs($user)
        ->post('/foco/notes', ['notes' => 'Revisar teoria dos conjuntos'])
        ->assertSuccessful()
        ->assertJson(['saved' => true]);

    expect($session->refresh()->notes)->toBe('Revisar teoria dos conjuntos');
});

test('saving notes without an active session conflicts instead of failing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/foco/notes', ['notes' => 'nada'])
        ->assertStatus(409);
});

test('the focus screen reports the minutes studied today against the daily goal', function () {
    $user = User::factory()->create();
    StudyPlan::factory()->for($user)->active()->create(['weekly_goal_minutes' => 700]);
    StudySession::factory()->for($user)->completed()->create([
        'accumulated_seconds' => 1800,
        'break_seconds' => 600,
        'ended_at' => now(),
    ]);

    // Por padrão o descanso não conta: 30 minutos de foco, não 40.
    $this->actingAs($user)
        ->get('/foco')
        ->assertInertia(fn ($page) => $page
            ->where('today.minutes', 30)
            ->where('today.goalMinutes', 100));
});

test('breaks join the studied time when the user asks for it', function () {
    $user = User::factory()->create();
    PomodoroSetting::factory()->for($user)->create(['exclude_breaks_from_study_time' => false]);
    StudyPlan::factory()->for($user)->active()->create(['weekly_goal_minutes' => 700]);
    StudySession::factory()->for($user)->completed()->create([
        'accumulated_seconds' => 1800,
        'break_seconds' => 600,
        'ended_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/foco')
        ->assertInertia(fn ($page) => $page->where('today.minutes', 40));
});

test('today pomodoros counts closed cycles, not sessions', function () {
    $user = User::factory()->create();
    StudyPlan::factory()->for($user)->active()->create();
    StudySession::factory()->for($user)->completed()->create([
        'completed_cycles' => 3,
        'ended_at' => now(),
    ]);
    StudySession::factory()->for($user)->completed()->create([
        'completed_cycles' => 2,
        'ended_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/foco')
        ->assertInertia(fn ($page) => $page->where('todayPomodoros', 5));
});

test('today pomodoros includes the cycles already closed in the running session', function () {
    $user = User::factory()->create();
    StudyPlan::factory()->for($user)->active()->create();
    StudySession::factory()->for($user)->completed()->create([
        'completed_cycles' => 1,
        'ended_at' => now(),
    ]);
    StudySession::factory()->for($user)->create(['completed_cycles' => 2]);

    $this->actingAs($user)
        ->get('/foco')
        ->assertInertia(fn ($page) => $page->where('todayPomodoros', 3));
});

test('completing a session keeps the notes typed during it', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();
    $session = StudySession::factory()->for($user)->create([
        'notes' => 'Revisar teorema espectral',
    ]);

    // O formulário de encerramento não manda `notes` — quem as gravou foi
    // o autosave em /foco/notes.
    $this->actingAs($user)
        ->post('/foco/complete', [
            'subject_id' => $subject->id,
            'activity_type' => ActivityType::Reading->value,
        ]);

    expect($session->refresh()->notes)->toBe('Revisar teorema espectral');
});

test('completing a session can still replace the notes when they are sent', function () {
    $user = User::factory()->create();
    StudyPlan::factory()->for($user)->active()->create();
    $session = StudySession::factory()->for($user)->create(['notes' => 'rascunho']);

    $this->actingAs($user)->post('/foco/complete', ['notes' => 'versão final']);

    expect($session->refresh()->notes)->toBe('versão final');
});
