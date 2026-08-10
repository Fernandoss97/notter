<?php

use App\Actions\Study\AdvanceFocusPhase;
use App\Actions\Study\CloseAbandonedFocusSession;
use App\Actions\Study\Exceptions\InvalidFocusSessionTransitionException;
use App\Actions\Study\PauseFocusSession;
use App\Actions\Study\ResolveCurrentFocusSession;
use App\Models\Enums\FocusPhase;
use App\Models\Enums\FocusSessionState;
use App\Models\PomodoroSetting;
use App\Models\StudySession;
use App\Models\User;

function settings(array $overrides = []): PomodoroSetting
{
    return PomodoroSetting::factory()->create([
        'focus_minutes' => 50,
        'short_break_minutes' => 10,
        'long_break_minutes' => 25,
        'cycles_before_long_break' => 4,
        'auto_start_break' => true,
        'auto_start_focus' => false,
        ...$overrides,
    ]);
}

function expiredFocusSession(array $overrides = []): StudySession
{
    return StudySession::factory()->create([
        'phase' => FocusPhase::Focus,
        'planned_seconds' => 50 * 60,
        'phase_accumulated_seconds' => 50 * 60,
        'state' => FocusSessionState::Paused,
        'resumed_at' => null,
        ...$overrides,
    ]);
}

test('finishing a focus phase opens a short break and counts the cycle', function () {
    $session = expiredFocusSession();

    $advanced = (new AdvanceFocusPhase)->handle($session, settings());

    expect($advanced->phase)->toBe(FocusPhase::ShortBreak)
        ->and($advanced->completed_cycles)->toBe(1)
        ->and($advanced->planned_seconds)->toBe(10 * 60)
        ->and($advanced->phase_accumulated_seconds)->toBe(0)
        ->and($advanced->state)->toBe(FocusSessionState::Running);
});

test('the fourth cycle opens a long break instead', function () {
    $session = expiredFocusSession(['completed_cycles' => 3]);

    $advanced = (new AdvanceFocusPhase)->handle($session, settings());

    expect($advanced->phase)->toBe(FocusPhase::LongBreak)
        ->and($advanced->planned_seconds)->toBe(25 * 60)
        ->and($advanced->completed_cycles)->toBe(4);
});

test('a break that ends returns to focus with the configured duration', function () {
    $session = StudySession::factory()->onBreak()->create([
        'planned_seconds' => 10 * 60,
        'phase_accumulated_seconds' => 10 * 60,
        'state' => FocusSessionState::Paused,
        'resumed_at' => null,
    ]);

    $advanced = (new AdvanceFocusPhase)->handle($session, settings());

    expect($advanced->phase)->toBe(FocusPhase::Focus)
        ->and($advanced->planned_seconds)->toBe(50 * 60)
        ->and($advanced->completed_cycles)->toBe(1);
});

test('auto start settings decide whether the next phase runs or waits', function () {
    $waits = (new AdvanceFocusPhase)->handle(
        expiredFocusSession(),
        settings(['auto_start_break' => false]),
    );

    expect($waits->state)->toBe(FocusSessionState::Paused)
        ->and($waits->resumed_at)->toBeNull();

    $runs = (new AdvanceFocusPhase)->handle(
        expiredFocusSession(),
        settings(['auto_start_break' => true]),
    );

    expect($runs->state)->toBe(FocusSessionState::Running)
        ->and($runs->resumed_at)->not->toBeNull();
});

test('advancing before the phase expires is refused', function () {
    $session = StudySession::factory()->create([
        'planned_seconds' => 50 * 60,
        'phase_accumulated_seconds' => 60,
        'state' => FocusSessionState::Paused,
        'resumed_at' => null,
    ]);

    (new AdvanceFocusPhase)->handle($session, settings());
})->throws(InvalidFocusSessionTransitionException::class);

test('a break can be skipped early but a focus phase cannot', function () {
    $onBreak = StudySession::factory()->onBreak()->create([
        'phase_accumulated_seconds' => 30,
        'state' => FocusSessionState::Paused,
        'resumed_at' => null,
    ]);

    $advanced = (new AdvanceFocusPhase)->handle($onBreak, settings(), skip: true);
    expect($advanced->phase)->toBe(FocusPhase::Focus);

    $focusing = StudySession::factory()->create([
        'planned_seconds' => 50 * 60,
        'phase_accumulated_seconds' => 30,
        'state' => FocusSessionState::Paused,
        'resumed_at' => null,
    ]);

    expect(fn () => (new AdvanceFocusPhase)->handle($focusing, settings(), skip: true))
        ->toThrow(InvalidFocusSessionTransitionException::class);
});

test('the live segment of a finished focus phase lands in the focus accumulator', function () {
    $this->freezeTime();
    $session = StudySession::factory()->create([
        'planned_seconds' => 60,
        'accumulated_seconds' => 0,
        'phase_accumulated_seconds' => 0,
        'resumed_at' => now(),
    ]);

    $this->travel(60)->seconds();

    $advanced = (new AdvanceFocusPhase)->handle($session, settings());

    expect($advanced->accumulated_seconds)->toBe(60)
        ->and($advanced->break_seconds)->toBe(0);
});

test('the live segment of a finished break lands in the break accumulator', function () {
    $this->freezeTime();
    $session = StudySession::factory()->onBreak()->create([
        'planned_seconds' => 60,
        'accumulated_seconds' => 3000,
        'phase_accumulated_seconds' => 0,
        'resumed_at' => now(),
    ]);

    $this->travel(60)->seconds();

    $advanced = (new AdvanceFocusPhase)->handle($session, settings());

    expect($advanced->accumulated_seconds)->toBe(3000)
        ->and($advanced->break_seconds)->toBe(60);
});

test('pausing during a break never inflates the focus time', function () {
    $this->freezeTime();
    $session = StudySession::factory()->onBreak()->create([
        'accumulated_seconds' => 3000,
        'break_seconds' => 0,
        'phase_accumulated_seconds' => 0,
        'resumed_at' => now(),
    ]);

    $this->travel(120)->seconds();

    $paused = (new PauseFocusSession)->handle($session);

    expect($paused->accumulated_seconds)->toBe(3000)
        ->and($paused->break_seconds)->toBe(120)
        ->and($paused->phase_accumulated_seconds)->toBe(120);
});

test('an abandoned running session is closed with the time capped at the plan', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $session = StudySession::factory()->for($user)->create([
        'planned_seconds' => 50 * 60,
        'accumulated_seconds' => 0,
        'phase_accumulated_seconds' => 0,
        'resumed_at' => now(),
    ]);

    $this->travel(9)->hours();

    $resolved = app(ResolveCurrentFocusSession::class)->handle($user);

    expect($resolved)->toBeNull()
        ->and($session->refresh()->state)->toBe(FocusSessionState::Completed)
        ->and($session->accumulated_seconds)->toBe(50 * 60)
        ->and($session->ended_at?->timestamp)->toBe(now()->subHours(9)->addMinutes(50)->timestamp);
});

test('a paused session left untouched for too long is closed', function () {
    $user = User::factory()->create();
    $session = StudySession::factory()->for($user)->paused()->create([
        'accumulated_seconds' => 600,
        'phase_accumulated_seconds' => 600,
    ]);
    // `updated_at` não é fillable: só a query builder crua envelhece a linha.
    StudySession::query()
        ->whereKey($session->id)
        ->update(['updated_at' => now()->subHours(StudySession::STALE_IDLE_HOURS + 1)]);

    expect(app(ResolveCurrentFocusSession::class)->handle($user))->toBeNull()
        ->and($session->refresh()->state)->toBe(FocusSessionState::Completed)
        ->and($session->accumulated_seconds)->toBe(600);
});

test('a session paused near midnight is not treated as abandoned', function () {
    $this->travelTo(today()->setTime(23, 50));
    $user = User::factory()->create();
    StudySession::factory()->for($user)->paused()->create();

    $this->travelTo(today()->addDay()->setTime(0, 5));

    expect(app(ResolveCurrentFocusSession::class)->handle($user))->not->toBeNull();
});

test('closing an abandoned break does not add the overflow to the focus time', function () {
    $this->freezeTime();
    $session = StudySession::factory()->onBreak()->create([
        'planned_seconds' => 10 * 60,
        'accumulated_seconds' => 3000,
        'break_seconds' => 0,
        'resumed_at' => now(),
    ]);

    $this->travel(5)->hours();

    $closed = app(CloseAbandonedFocusSession::class)->handle($session);

    expect($closed->accumulated_seconds)->toBe(3000)
        ->and($closed->break_seconds)->toBe(10 * 60);
});
