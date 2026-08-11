<?php

use App\Models\PomodoroSetting;
use App\Models\User;

test('guests are redirected away from the pomodoro settings screen', function () {
    $this->get('/configuracoes')->assertRedirect('/login');
});

test('the settings screen creates a default row for a user without one yet', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/configuracoes')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('settings/pomodoro')
            ->where('settings.focusMinutes', 50)
            ->where('settings.cyclesBeforeLongBreak', 4)
            ->where('settings.dailyGoalMinutes', PomodoroSetting::DEFAULT_DAILY_GOAL_MINUTES));

    expect($user->pomodoroSetting()->exists())->toBeTrue();
});

test('the settings screen renders the user existing preferences', function () {
    $user = User::factory()->create();
    PomodoroSetting::factory()->for($user)->create(['focus_minutes' => 25]);

    $this->actingAs($user)
        ->get('/configuracoes')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('settings.focusMinutes', 25));
});

test('a user can update their pomodoro preferences', function () {
    $user = User::factory()->create();
    PomodoroSetting::factory()->for($user)->create();

    $this->actingAs($user)
        ->put('/configuracoes', [
            'focus_minutes' => 25,
            'short_break_minutes' => 5,
            'long_break_minutes' => 15,
            'cycles_before_long_break' => 3,
            'daily_goal_minutes' => 180,
            'auto_start_break' => false,
            'auto_start_focus' => true,
            'log_session_on_finish' => false,
            'silent_mode_during_focus' => false,
            'exclude_breaks_from_study_time' => true,
        ])
        ->assertRedirect(route('pomodoro-settings.edit'));

    $settings = $user->pomodoroSetting()->sole();
    expect($settings->focus_minutes)->toBe(25)
        ->and($settings->daily_goal_minutes)->toBe(180)
        ->and($settings->cycles_before_long_break)->toBe(3)
        ->and($settings->auto_start_focus)->toBeTrue()
        ->and($settings->auto_start_break)->toBeFalse()
        ->and($settings->exclude_breaks_from_study_time)->toBeTrue();
});

test('updating creates the settings row when the user does not have one yet', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/configuracoes', [
        'focus_minutes' => 45,
        'short_break_minutes' => 10,
        'long_break_minutes' => 20,
        'cycles_before_long_break' => 4,
        'daily_goal_minutes' => 120,
        'auto_start_break' => true,
        'auto_start_focus' => false,
        'log_session_on_finish' => true,
        'silent_mode_during_focus' => true,
        'exclude_breaks_from_study_time' => false,
    ]);

    expect($user->pomodoroSetting()->sole()->focus_minutes)->toBe(45);
});

test('the daily goal has to be a day someone could actually study', function () {
    $user = User::factory()->create();

    $payload = [
        'focus_minutes' => 50,
        'short_break_minutes' => 10,
        'long_break_minutes' => 25,
        'cycles_before_long_break' => 4,
        'auto_start_break' => true,
        'auto_start_focus' => false,
        'log_session_on_finish' => true,
        'silent_mode_during_focus' => true,
        'exclude_breaks_from_study_time' => false,
    ];

    $this->actingAs($user)
        ->put('/configuracoes', [...$payload, 'daily_goal_minutes' => 0])
        ->assertInvalid(['daily_goal_minutes']);

    $this->actingAs($user)
        ->put('/configuracoes', [...$payload, 'daily_goal_minutes' => 1500])
        ->assertInvalid(['daily_goal_minutes']);
});

test('cycles_before_long_break is restricted to the allowed set', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/configuracoes', [
            'focus_minutes' => 50,
            'short_break_minutes' => 10,
            'long_break_minutes' => 25,
            'cycles_before_long_break' => 7,
            'daily_goal_minutes' => 120,
            'auto_start_break' => true,
            'auto_start_focus' => false,
            'log_session_on_finish' => true,
            'silent_mode_during_focus' => true,
            'exclude_breaks_from_study_time' => false,
        ])
        ->assertInvalid(['cycles_before_long_break']);
});
