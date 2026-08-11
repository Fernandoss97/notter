<?php

namespace App\Models;

use Database\Factories\PomodoroSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $focus_minutes
 * @property int $short_break_minutes
 * @property int $long_break_minutes
 * @property int $cycles_before_long_break
 * @property int $daily_goal_minutes
 * @property bool $auto_start_break
 * @property bool $auto_start_focus
 * @property bool $log_session_on_finish
 * @property bool $silent_mode_during_focus
 * @property bool $exclude_breaks_from_study_time
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'focus_minutes', 'short_break_minutes', 'long_break_minutes', 'cycles_before_long_break',
    'daily_goal_minutes', 'auto_start_break', 'auto_start_focus', 'log_session_on_finish',
    'silent_mode_during_focus', 'exclude_breaks_from_study_time',
])]
class PomodoroSetting extends Model
{
    /** @use HasFactory<PomodoroSettingFactory> */
    use HasFactory;

    /**
     * A meta de estudo de um dia, em minutos.
     *
     * Mora aqui e não no plano porque descreve a rotina de quem estuda, não
     * o concurso: trocar de plano não muda quantas horas cabem no seu dia.
     * Duas horas de padrão é o que a meta semanal típica do app (20h) daria
     * numa semana de cinco dias úteis; o teto de 16h é sanidade.
     */
    public const MIN_DAILY_GOAL_MINUTES = 5;

    public const MAX_DAILY_GOAL_MINUTES = 960;

    public const DEFAULT_DAILY_GOAL_MINUTES = 120;

    protected $attributes = [
        'focus_minutes' => 50,
        'short_break_minutes' => 10,
        'long_break_minutes' => 25,
        'cycles_before_long_break' => 4,
        'daily_goal_minutes' => self::DEFAULT_DAILY_GOAL_MINUTES,
        'auto_start_break' => true,
        'auto_start_focus' => false,
        'log_session_on_finish' => true,
        'silent_mode_during_focus' => true,
        'exclude_breaks_from_study_time' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'focus_minutes' => 'integer',
            'short_break_minutes' => 'integer',
            'long_break_minutes' => 'integer',
            'cycles_before_long_break' => 'integer',
            'daily_goal_minutes' => 'integer',
            'auto_start_break' => 'boolean',
            'auto_start_focus' => 'boolean',
            'log_session_on_finish' => 'boolean',
            'silent_mode_during_focus' => 'boolean',
            'exclude_breaks_from_study_time' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
