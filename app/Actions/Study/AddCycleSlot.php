<?php

namespace App\Actions\Study;

use App\Actions\Study\Exceptions\InvalidCycleTransitionException;
use App\Models\CycleSlot;
use App\Models\StudyPlan;
use App\Models\Subject;

class AddCycleSlot
{
    /**
     * Põe uma disciplina no rodízio, no fim da fila.
     *
     * Entrar no fim é deliberado: quem acabou de acrescentar a matéria
     * ainda vai terminar o bloco que está fazendo, e furar a fila
     * inverteria a continuidade que o ciclo existe para proteger. Mover
     * para cima é uma reordenação explícita.
     *
     * @throws InvalidCycleTransitionException
     */
    public function handle(StudyPlan $plan, Subject $subject, ?int $blockMinutes = null): CycleSlot
    {
        if ($subject->study_plan_id !== $plan->id) {
            throw InvalidCycleTransitionException::subjectOutsidePlan();
        }

        if ($plan->cycleSlots()->where('subject_id', $subject->id)->exists()) {
            throw InvalidCycleTransitionException::subjectAlreadyInCycle();
        }

        return $plan->cycleSlots()->create([
            'subject_id' => $subject->id,
            'position' => (int) $plan->cycleSlots()->max('position') + 1,
            'block_minutes' => $blockMinutes ?? CycleSlot::MIN_BLOCK_MINUTES,
        ]);
    }
}
