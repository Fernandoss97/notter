<?php

namespace App\Actions\Study;

use App\Actions\Study\Exceptions\InvalidFocusSessionTransitionException;
use App\Models\Enums\FocusPhase;
use App\Models\Enums\FocusSessionState;
use App\Models\PomodoroSetting;
use App\Models\StudySession;

class AdvanceFocusPhase
{
    /**
     * Fecha a fase corrente e abre a próxima do ciclo pomodoro (B2):
     * foco -> pausa (longa a cada N ciclos) -> foco.
     *
     * Quem detecta o fim do ciclo é o cliente, mas quem decide é o
     * servidor: sem `isPhaseExpired()` duas abas abertas avançariam duas
     * vezes o mesmo ciclo. Pular um intervalo antes da hora (`$skip`) é a
     * única transição adiantada legítima — ela não infla tempo de foco nem
     * a contagem de ciclos.
     */
    public function handle(StudySession $session, PomodoroSetting $settings, bool $skip = false): StudySession
    {
        if ($session->state === FocusSessionState::Completed) {
            throw InvalidFocusSessionTransitionException::alreadyCompleted();
        }

        if (! $session->isPhaseExpired() && ! ($skip && $session->phase->isBreak())) {
            throw InvalidFocusSessionTransitionException::phaseNotFinished();
        }

        $liveSeconds = $session->liveSeconds();
        $wasFocus = $session->phase === FocusPhase::Focus;
        $completedCycles = $session->completed_cycles + ($wasFocus ? 1 : 0);

        [$nextPhase, $plannedMinutes, $autoStart] = $wasFocus
            ? $this->breakAfter($completedCycles, $settings)
            : [FocusPhase::Focus, $settings->focus_minutes, $settings->auto_start_focus];

        $session->update([
            'phase' => $nextPhase,
            'completed_cycles' => $completedCycles,
            'accumulated_seconds' => $session->accumulated_seconds + ($wasFocus ? $liveSeconds : 0),
            'break_seconds' => $session->break_seconds + ($wasFocus ? 0 : $liveSeconds),
            'phase_accumulated_seconds' => 0,
            'planned_seconds' => $plannedMinutes * 60,
            'state' => $autoStart ? FocusSessionState::Running : FocusSessionState::Paused,
            'resumed_at' => $autoStart ? now() : null,
        ]);

        return $session->refresh();
    }

    /**
     * A pausa que sucede o ciclo recém-fechado: longa quando o número de
     * ciclos concluídos fecha uma volta completa.
     *
     * @return array{0: FocusPhase, 1: int, 2: bool}
     */
    private function breakAfter(int $completedCycles, PomodoroSetting $settings): array
    {
        $isLong = $settings->cycles_before_long_break > 0
            && $completedCycles % $settings->cycles_before_long_break === 0;

        return [
            $isLong ? FocusPhase::LongBreak : FocusPhase::ShortBreak,
            $isLong ? $settings->long_break_minutes : $settings->short_break_minutes,
            $settings->auto_start_break,
        ];
    }
}
