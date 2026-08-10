<?php

namespace App\Actions\Study;

use App\Actions\Study\Exceptions\InvalidFocusSessionTransitionException;
use App\Models\Enums\ActivityType;
use App\Models\Enums\FocusPhase;
use App\Models\Enums\FocusSessionState;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;

class CompleteFocusSession
{
    /**
     * Encerra a sessão e registra os dados coletados ao final (B4): a
     * disciplina/tópico só precisam ser confirmados aqui, não no início da
     * sessão. Congela o segmento vivo no acumulador da fase corrente.
     *
     * Sem disciplina, nada do que já estava na sessão é sobrescrito — é o
     * encerramento rápido de quem desligou "registrar sessão ao encerrar"
     * e não quer perder a disciplina escolhida lá no início.
     */
    public function handle(
        StudySession $session,
        ?Subject $subject = null,
        ?Topic $topic = null,
        ?ActivityType $activityType = null,
        ?int $exercisesTotal = null,
        ?int $exercisesCorrect = null,
        ?int $pagesRead = null,
        ?string $notes = null,
    ): StudySession {
        if ($session->state === FocusSessionState::Completed) {
            throw InvalidFocusSessionTransitionException::alreadyCompleted();
        }

        $liveSeconds = $session->liveSeconds();
        $isFocus = $session->phase === FocusPhase::Focus;

        $attributes = [
            'state' => FocusSessionState::Completed,
            'accumulated_seconds' => $session->accumulated_seconds + ($isFocus ? $liveSeconds : 0),
            'break_seconds' => $session->break_seconds + ($isFocus ? 0 : $liveSeconds),
            'phase_accumulated_seconds' => $session->phase_accumulated_seconds + $liveSeconds,
            'resumed_at' => null,
            'ended_at' => now(),
        ];

        if ($subject instanceof Subject) {
            $attributes['subject_id'] = $subject->id;
            $attributes['topic_id'] = $topic?->id;
            $attributes['exercises_total'] = $exercisesTotal;
            $attributes['exercises_correct'] = $exercisesCorrect;
            $attributes['pages_read'] = $pagesRead;
        }

        if ($activityType instanceof ActivityType) {
            $attributes['activity_type'] = $activityType;
        }

        if ($notes !== null) {
            $attributes['notes'] = $notes;
        }

        DB::transaction(function () use ($session, $attributes, $subject, $topic, $exercisesTotal, $exercisesCorrect, $pagesRead): void {
            $session->update($attributes);

            // As questões e as páginas da sessão sobem para a tarefa, que é
            // onde o total acumulado vive (F2). Sem disciplina confirmada não
            // há o que somar: o vínculo com o tópico não foi confirmado no
            // encerramento.
            if (! $subject instanceof Subject || ! $topic instanceof Topic) {
                return;
            }

            if ($exercisesTotal) {
                $topic->addExercises($exercisesTotal, $exercisesCorrect ?? 0);
            }

            if ($pagesRead) {
                $topic->addPages($pagesRead);
            }
        });

        return $session->refresh();
    }
}
