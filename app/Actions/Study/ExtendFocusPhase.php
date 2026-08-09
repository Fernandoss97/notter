<?php

namespace App\Actions\Study;

use App\Actions\Study\Exceptions\InvalidFocusSessionTransitionException;
use App\Models\Enums\FocusSessionState;
use App\Models\StudySession;

class ExtendFocusPhase
{
    /** Quanto um clique em "+5 min" acrescenta à fase corrente. */
    public const EXTENSION_MINUTES = 5;

    /**
     * Teto da fase depois das extensões. Não é uma regra de produto, é
     * uma trava: `isStale()` usa a duração planejada para reconhecer uma
     * sessão abandonada, e uma fase esticada sem limite faria uma aba
     * esquecida virar tempo de estudo.
     */
    public const MAX_PHASE_SECONDS = 4 * 3600;

    /**
     * Estica a fase de foco corrente (B2). Serve ao caso em que o tempo
     * acaba no meio de um raciocínio: em vez de fechar o ciclo e perder o
     * fio, o pomodoro ganha alguns minutos.
     *
     * Só o foco é esticável — adiar o fim de um intervalo é adiar o
     * estudo, e o ciclo já oferece "pular intervalo" para o oposto.
     */
    public function handle(StudySession $session, int $minutes = self::EXTENSION_MINUTES): StudySession
    {
        if ($session->state === FocusSessionState::Completed) {
            throw InvalidFocusSessionTransitionException::alreadyCompleted();
        }

        if ($session->phase->isBreak()) {
            throw InvalidFocusSessionTransitionException::breakNotExtendable();
        }

        $extended = $session->planned_seconds + $minutes * 60;

        if ($extended > self::MAX_PHASE_SECONDS) {
            throw InvalidFocusSessionTransitionException::phaseTooLong();
        }

        $session->update(['planned_seconds' => $extended]);

        return $session->refresh();
    }
}
