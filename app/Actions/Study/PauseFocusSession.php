<?php

namespace App\Actions\Study;

use App\Actions\Study\Exceptions\InvalidFocusSessionTransitionException;
use App\Models\Enums\FocusPhase;
use App\Models\Enums\FocusSessionState;
use App\Models\StudySession;

class PauseFocusSession
{
    /**
     * Pausa uma sessão em andamento (B1/B7): congela o segmento corrente
     * e limpa `resumed_at`, que é o que StudySession::liveSeconds() usa
     * para parar de contar tempo vivo.
     *
     * O segmento vai para o acumulador da fase certa — pausar durante um
     * intervalo não pode inflar o tempo de foco que vai para o dashboard.
     */
    public function handle(StudySession $session): StudySession
    {
        if ($session->state !== FocusSessionState::Running) {
            throw InvalidFocusSessionTransitionException::notRunning();
        }

        $liveSeconds = $session->liveSeconds();

        $session->update([
            'state' => FocusSessionState::Paused,
            'accumulated_seconds' => $session->accumulated_seconds
                + ($session->phase === FocusPhase::Focus ? $liveSeconds : 0),
            'break_seconds' => $session->break_seconds
                + ($session->phase->isBreak() ? $liveSeconds : 0),
            'phase_accumulated_seconds' => $session->phase_accumulated_seconds + $liveSeconds,
            'resumed_at' => null,
        ]);

        return $session->refresh();
    }
}
