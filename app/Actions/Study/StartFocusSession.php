<?php

namespace App\Actions\Study;

use App\Actions\Study\Exceptions\InvalidFocusSessionTransitionException;
use App\Models\Enums\FocusSessionState;
use App\Models\PomodoroSetting;
use App\Models\StudyPlan;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;

class StartFocusSession
{
    /**
     * Inicia uma sessão de foco para o usuário (B1/B7). `planned_seconds`
     * é um snapshot da duração de foco configurada no momento do início —
     * mudanças posteriores em PomodoroSetting não afetam sessões em
     * andamento. Só uma sessão não concluída por usuário por vez.
     */
    public function handle(
        User $user,
        StudyPlan $studyPlan,
        ?Subject $subject = null,
        ?Topic $topic = null,
    ): StudySession {
        $hasActiveSession = $user->studySessions()
            ->where('state', '!=', FocusSessionState::Completed)
            ->exists();

        if ($hasActiveSession) {
            throw InvalidFocusSessionTransitionException::alreadyActive();
        }

        $pomodoroSetting = $user->pomodoroSetting()->first();
        $focusMinutes = $pomodoroSetting instanceof PomodoroSetting
            ? $pomodoroSetting->focus_minutes
            : (new PomodoroSetting)->focus_minutes;

        $now = now();

        return StudySession::create([
            'user_id' => $user->id,
            'study_plan_id' => $studyPlan->id,
            'subject_id' => $subject?->id,
            'topic_id' => $topic?->id,
            'state' => FocusSessionState::Running,
            'planned_seconds' => $focusMinutes * 60,
            'started_at' => $now,
            'resumed_at' => $now,
        ]);
    }
}
