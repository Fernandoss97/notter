<?php

namespace App\Http\Requests\Study;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePomodoroSettingRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'focus_minutes' => ['required', 'integer', 'min:1', 'max:180'],
            'short_break_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'long_break_minutes' => ['required', 'integer', 'min:1', 'max:90'],
            'cycles_before_long_break' => ['required', Rule::in([2, 3, 4, 5])],
            'auto_start_break' => ['required', 'boolean'],
            'auto_start_focus' => ['required', 'boolean'],
            'log_session_on_finish' => ['required', 'boolean'],
            'silent_mode_during_focus' => ['required', 'boolean'],
            'exclude_breaks_from_study_time' => ['required', 'boolean'],
        ];
    }
}
