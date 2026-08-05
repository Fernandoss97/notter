<?php

namespace App\Actions\Study;

use App\Actions\Study\Exceptions\InvalidFocusSessionTransitionException;
use App\Models\Enums\FocusSessionState;
use App\Models\StudySession;

class ResumeFocusSession
{
    /**
     * Retoma uma sessão pausada (B1/B7): reabre um novo segmento vivo a
     * partir de agora, mantendo `accumulated_seconds` do que já foi
     * decorrido antes da pausa.
     */
    public function handle(StudySession $session): StudySession
    {
        if ($session->state !== FocusSessionState::Paused) {
            throw InvalidFocusSessionTransitionException::notPaused();
        }

        $session->update([
            'state' => FocusSessionState::Running,
            'resumed_at' => now(),
        ]);

        return $session->refresh();
    }
}
