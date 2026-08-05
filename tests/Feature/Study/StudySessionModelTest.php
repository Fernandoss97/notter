<?php

use App\Models\Enums\FocusSessionState;
use App\Models\StudySession;

test('elapsedSeconds adds the live segment while running', function () {
    $this->freezeTime();

    $session = StudySession::factory()->create([
        'state' => FocusSessionState::Running,
        'accumulated_seconds' => 100,
        'resumed_at' => now(),
    ]);

    $this->travel(45)->seconds();

    expect($session->elapsedSeconds())->toBe(145);
});

test('elapsedSeconds does not add live time while paused', function () {
    $session = StudySession::factory()->paused()->create([
        'accumulated_seconds' => 300,
    ]);

    $this->travel(1)->minutes();

    expect($session->elapsedSeconds())->toBe(300);
});

test('elapsedSeconds does not add live time once completed', function () {
    $session = StudySession::factory()->completed()->create([
        'accumulated_seconds' => 3000,
    ]);

    $this->travel(1)->minutes();

    expect($session->elapsedSeconds())->toBe(3000);
});

test('accuracyPercent is null without recorded exercises', function () {
    $session = StudySession::factory()->create(['exercises_total' => null]);

    expect($session->accuracyPercent())->toBeNull();
});

test('accuracyPercent rounds correct over total', function () {
    $session = StudySession::factory()->create([
        'exercises_total' => 20,
        'exercises_correct' => 16,
    ]);

    expect($session->accuracyPercent())->toBe(80);
});
