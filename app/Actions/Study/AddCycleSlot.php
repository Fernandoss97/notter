<?php

namespace App\Actions\Study;

use App\Actions\Study\Exceptions\InvalidCycleTransitionException;
use App\Models\CycleSlot;
use App\Models\StudyPlan;
use App\Models\Subject;

class AddCycleSlot
{
    /**
     * Quanto dura a imersão inicial de uma disciplina nova (§4). Duas
     * semanas é o horizonte em que "matéria nova" ainda descreve a
     * situação; passado isso, ela é uma matéria como as outras.
     */
    public const BOOST_WEEKS = 2;

    /**
     * Põe uma disciplina no rodízio, no fim da fila.
     *
     * Entrar no fim é deliberado: quem acabou de acrescentar a matéria
     * ainda vai terminar o bloco que está fazendo, e furar a fila
     * inverteria a continuidade que o ciclo existe para proteger. Mover
     * para cima é uma reordenação explícita.
     *
     * Com `$boost`, a matéria entra em imersão inicial: bloco no teto
     * recomendado e uma data de validade para o reforço. A validade é o
     * ponto — um peso declarado sem prazo nunca é revisto, que foi o que
     * condenou a prioridade de disciplina neste projeto.
     *
     * @throws InvalidCycleTransitionException
     */
    public function handle(
        StudyPlan $plan,
        Subject $subject,
        ?int $blockMinutes = null,
        bool $boost = false,
    ): CycleSlot {
        if ($subject->study_plan_id !== $plan->id) {
            throw InvalidCycleTransitionException::subjectOutsidePlan();
        }

        if ($plan->cycleSlots()->where('subject_id', $subject->id)->exists()) {
            throw InvalidCycleTransitionException::subjectAlreadyInCycle();
        }

        $default = $boost
            ? CycleSlot::RECOMMENDED_MAX_BLOCK_MINUTES
            : CycleSlot::MIN_BLOCK_MINUTES;

        return $plan->cycleSlots()->create([
            'subject_id' => $subject->id,
            'position' => (int) $plan->cycleSlots()->max('position') + 1,
            'block_minutes' => $blockMinutes ?? $default,
            'boost_until' => $boost ? today()->addWeeks(self::BOOST_WEEKS) : null,
        ]);
    }
}
