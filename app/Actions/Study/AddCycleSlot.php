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
     * Com `$boost`, a matéria entra em imersão inicial: uma data de
     * validade para o reforço, que CycleSlot soma ao bloco enquanto ela
     * vale. O reforço não é gravado na duração — assim ele some sozinho
     * quando a data passa, em vez de virar um peso permanente que ninguém
     * lembra de desfazer, que foi o que condenou a prioridade de
     * disciplina neste projeto.
     *
     * @throws InvalidCycleTransitionException
     */
    public function handle(
        StudyPlan $plan,
        Subject $subject,
        ?int $blockMinutes = null,
        bool $boost = false,
        ?int $blocksPerLap = null,
    ): CycleSlot {
        if ($subject->study_plan_id !== $plan->id) {
            throw InvalidCycleTransitionException::subjectOutsidePlan();
        }

        if ($plan->cycleSlots()->where('subject_id', $subject->id)->exists()) {
            throw InvalidCycleTransitionException::subjectAlreadyInCycle();
        }

        return $plan->cycleSlots()->create([
            'subject_id' => $subject->id,
            'position' => (int) $plan->cycleSlots()->max('position') + 1,
            'block_minutes' => $blockMinutes ?? CycleSlot::DEFAULT_BLOCK_MINUTES,
            'blocks_per_lap' => $blocksPerLap ?? CycleSlot::DEFAULT_BLOCKS_PER_LAP,
            'boost_until' => $boost ? today()->addWeeks(self::BOOST_WEEKS) : null,
        ]);
    }
}
