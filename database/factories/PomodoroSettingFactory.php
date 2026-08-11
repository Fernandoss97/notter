<?php

namespace Database\Factories;

use App\Models\PomodoroSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PomodoroSetting>
 */
class PomodoroSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'focus_minutes' => 50,
            'short_break_minutes' => 10,
            'long_break_minutes' => 25,
            'cycles_before_long_break' => 4,
            'daily_goal_minutes' => PomodoroSetting::DEFAULT_DAILY_GOAL_MINUTES,
            'auto_start_break' => true,
            'auto_start_focus' => false,
            'log_session_on_finish' => true,
            'silent_mode_during_focus' => true,
            'exclude_breaks_from_study_time' => true,
        ];
    }
}
