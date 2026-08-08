<?php

namespace App\Http\Controllers;

use App\Http\Requests\Study\UpdatePomodoroSettingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PomodoroSettingController extends Controller
{
    public function edit(Request $request): Response
    {
        $settings = $request->user()->pomodoroSetting()->firstOrCreate([]);

        return Inertia::render('pomodoro/edit', [
            'settings' => [
                'focusMinutes' => $settings->focus_minutes,
                'shortBreakMinutes' => $settings->short_break_minutes,
                'longBreakMinutes' => $settings->long_break_minutes,
                'cyclesBeforeLongBreak' => $settings->cycles_before_long_break,
                'autoStartBreak' => $settings->auto_start_break,
                'autoStartFocus' => $settings->auto_start_focus,
                'logSessionOnFinish' => $settings->log_session_on_finish,
                'silentModeDuringFocus' => $settings->silent_mode_during_focus,
                'excludeBreaksFromStudyTime' => $settings->exclude_breaks_from_study_time,
            ],
        ]);
    }

    public function update(UpdatePomodoroSettingRequest $request): RedirectResponse
    {
        $settings = $request->user()->pomodoroSetting()->firstOrCreate([]);
        $settings->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Preferências de pomodoro salvas.')]);

        return to_route('pomodoro-settings.edit');
    }
}
