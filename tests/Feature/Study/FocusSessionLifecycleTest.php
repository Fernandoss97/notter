<?php

use App\Actions\Study\CompleteFocusSession;
use App\Actions\Study\Exceptions\InvalidFocusSessionTransitionException;
use App\Actions\Study\PauseFocusSession;
use App\Actions\Study\ResumeFocusSession;
use App\Actions\Study\StartFocusSession;
use App\Models\Enums\ActivityType;
use App\Models\Enums\FocusSessionState;
use App\Models\PomodoroSetting;
use App\Models\StudyPlan;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;

test('starting a session snapshots the user pomodoro focus duration', function () {
    $user = User::factory()->create();
    PomodoroSetting::factory()->for($user)->create(['focus_minutes' => 30]);
    $plan = StudyPlan::factory()->for($user)->active()->create();

    $session = (new StartFocusSession)->handle($user, $plan);

    expect($session->planned_seconds)->toBe(30 * 60)
        ->and($session->state)->toBe(FocusSessionState::Running)
        ->and($session->user_id)->toBe($user->id);
});

test('starting a session falls back to the default focus duration without settings', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();

    $session = (new StartFocusSession)->handle($user, $plan);

    expect($session->planned_seconds)->toBe((new PomodoroSetting)->focus_minutes * 60);
});

test('a user cannot start a second session while one is already active', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    (new StartFocusSession)->handle($user, $plan);

    (new StartFocusSession)->handle($user, $plan);
})->throws(InvalidFocusSessionTransitionException::class);

test('pausing a running session freezes the elapsed time and clears resumed_at', function () {
    $this->freezeTime();
    $session = StudySession::factory()->create([
        'state' => FocusSessionState::Running,
        'accumulated_seconds' => 0,
        'resumed_at' => now(),
    ]);

    $this->travel(90)->seconds();

    $paused = (new PauseFocusSession)->handle($session);

    expect($paused->state)->toBe(FocusSessionState::Paused)
        ->and($paused->accumulated_seconds)->toBe(90)
        ->and($paused->resumed_at)->toBeNull();
});

test('pausing a session that is not running throws', function () {
    $session = StudySession::factory()->paused()->create();

    (new PauseFocusSession)->handle($session);
})->throws(InvalidFocusSessionTransitionException::class);

test('resuming a paused session opens a new live segment', function () {
    $this->freezeTime();
    $session = StudySession::factory()->paused()->create(['accumulated_seconds' => 200]);

    $resumed = (new ResumeFocusSession)->handle($session);

    expect($resumed->state)->toBe(FocusSessionState::Running)
        ->and($resumed->resumed_at?->timestamp)->toBe(now()->timestamp)
        ->and($resumed->accumulated_seconds)->toBe(200);
});

test('resuming a session that is not paused throws', function () {
    $session = StudySession::factory()->create(['state' => FocusSessionState::Running]);

    (new ResumeFocusSession)->handle($session);
})->throws(InvalidFocusSessionTransitionException::class);

test('completing a session records the registration data and freezes elapsed time', function () {
    $this->freezeTime();
    $session = StudySession::factory()->create([
        'state' => FocusSessionState::Running,
        'accumulated_seconds' => 0,
        'resumed_at' => now(),
    ]);
    $subject = Subject::factory()->create();
    $topic = Topic::factory()->for($subject)->create();

    $this->travel(50)->minutes();

    $completed = (new CompleteFocusSession)->handle(
        $session,
        $subject,
        $topic,
        ActivityType::Exercises,
        exercisesTotal: 20,
        exercisesCorrect: 16,
    );

    expect($completed->state)->toBe(FocusSessionState::Completed)
        ->and($completed->accumulated_seconds)->toBe(50 * 60)
        ->and($completed->resumed_at)->toBeNull()
        ->and($completed->ended_at)->not->toBeNull()
        ->and($completed->subject_id)->toBe($subject->id)
        ->and($completed->topic_id)->toBe($topic->id)
        ->and($completed->activity_type)->toBe(ActivityType::Exercises)
        ->and($completed->exercises_total)->toBe(20)
        ->and($completed->exercises_correct)->toBe(16);
});

test('completing an already-completed session throws', function () {
    $session = StudySession::factory()->completed()->create();
    $subject = Subject::factory()->create();

    (new CompleteFocusSession)->handle($session, $subject, null, ActivityType::Reading);
})->throws(InvalidFocusSessionTransitionException::class);
