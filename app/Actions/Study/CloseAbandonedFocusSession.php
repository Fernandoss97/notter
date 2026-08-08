<?php

namespace App\Actions\Study;

use App\Models\Enums\FocusPhase;
use App\Models\Enums\FocusSessionState;
use App\Models\StudySession;

class CloseAbandonedFocusSession
{
    /**
     * Encerra uma sessão que ninguém fechou — aba fechada no meio do
     * ciclo, notebook fechado, sessão pausada esquecida.
     *
     * O segmento vivo entra capado na duração planejada da fase: quem
     * deixou a aba aberta a noite toda não estudou a noite toda, e esse
     * tempo iria direto para as estatísticas do dashboard. `ended_at`
     * recua para o momento em que a fase deveria ter acabado, para a
     * sessão cair no dia certo do histórico.
     */
    public function handle(StudySession $session): StudySession
    {
        $liveSeconds = $session->liveSecondsWithinPlan();
        $isFocus = $session->phase === FocusPhase::Focus;

        $session->update([
            'state' => FocusSessionState::Completed,
            'accumulated_seconds' => $session->accumulated_seconds + ($isFocus ? $liveSeconds : 0),
            'break_seconds' => $session->break_seconds + ($isFocus ? 0 : $liveSeconds),
            'phase_accumulated_seconds' => $session->phase_accumulated_seconds + $liveSeconds,
            'resumed_at' => null,
            'ended_at' => $session->resumed_at?->copy()->addSeconds($liveSeconds) ?? $session->updated_at ?? now(),
        ]);

        return $session->refresh();
    }
}
