<?php

namespace App\Concerns;

use App\Models\Enums\FocusSessionState;
use App\Models\StudySession;
use App\Models\User;
use Carbon\CarbonInterface;

trait SummarizesStudyTime
{
    /**
     * Minutos estudados numa janela, somando a sessão em andamento para o
     * número não travar enquanto o cronômetro roda.
     *
     * O tempo de intervalo entra ou não conforme a preferência do usuário
     * (`exclude_breaks_from_study_time`). A soma dos concluídos é feita em
     * SQL — daí os dois `SUM` numa tacada, em vez de recordedSeconds().
     */
    protected function minutesStudied(
        User $user,
        CarbonInterface $from,
        CarbonInterface $to,
        bool $excludeBreaks = false,
        ?StudySession $activeSession = null,
    ): int {
        /** @var object{focus_seconds: int|string|null, break_seconds: int|string|null}|null $totals */
        $totals = $user->studySessions()
            ->where('state', FocusSessionState::Completed)
            ->whereBetween('ended_at', [$from, $to])
            ->toBase()
            ->selectRaw('COALESCE(SUM(accumulated_seconds), 0) as focus_seconds')
            ->selectRaw('COALESCE(SUM(break_seconds), 0) as break_seconds')
            ->first();

        $seconds = (int) ($totals->focus_seconds ?? 0)
            + ($excludeBreaks ? 0 : (int) ($totals->break_seconds ?? 0));

        if ($activeSession instanceof StudySession && $activeSession->started_at->between($from, $to)) {
            $seconds += $activeSession->recordedSeconds($excludeBreaks);
        }

        return (int) round($seconds / 60);
    }
}
