<?php

namespace App\Actions\Study;

use App\Actions\Study\Exceptions\InvalidFocusSessionTransitionException;
use App\Models\Enums\FocusSessionState;
use App\Models\StudySession;

class PauseFocusSession
{
    /**
     * Pausa uma sessão em andamento (B1/B7): soma o segmento corrente em
     * `accumulated_seconds` e limpa `resumed_at`, que é o que
     * StudySession::elapsedSeconds() usa para parar de contar tempo vivo.
     */
    public function handle(StudySession $session): StudySession
    {
        if ($session->state !== FocusSessionState::Running) {
            throw InvalidFocusSessionTransitionException::notRunning();
        }

        $session->update([
            'state' => FocusSessionState::Paused,
            'accumulated_seconds' => $session->elapsedSeconds(),
            'resumed_at' => null,
        ]);

        return $session->refresh();
    }
}
