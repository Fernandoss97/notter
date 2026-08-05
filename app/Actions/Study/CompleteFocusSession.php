<?php

namespace App\Actions\Study;

use App\Actions\Study\Exceptions\InvalidFocusSessionTransitionException;
use App\Models\Enums\ActivityType;
use App\Models\Enums\FocusSessionState;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Topic;

class CompleteFocusSession
{
    /**
     * Encerra a sessão e registra os dados coletados ao final (B4): a
     * disciplina/tópico só precisam ser confirmados aqui, não no início da
     * sessão. Congela o tempo decorrido em `accumulated_seconds`.
     */
    public function handle(
        StudySession $session,
        Subject $subject,
        ?Topic $topic,
        ActivityType $activityType,
        ?int $exercisesTotal = null,
        ?int $exercisesCorrect = null,
    ): StudySession {
        if ($session->state === FocusSessionState::Completed) {
            throw InvalidFocusSessionTransitionException::alreadyCompleted();
        }

        $session->update([
            'state' => FocusSessionState::Completed,
            'accumulated_seconds' => $session->elapsedSeconds(),
            'resumed_at' => null,
            'ended_at' => now(),
            'subject_id' => $subject->id,
            'topic_id' => $topic?->id,
            'activity_type' => $activityType,
            'exercises_total' => $exercisesTotal,
            'exercises_correct' => $exercisesCorrect,
        ]);

        return $session->refresh();
    }
}
