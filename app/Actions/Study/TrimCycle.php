<?php

namespace App\Actions\Study;

use App\Models\Enums\CycleSlotState;
use App\Models\StudyPlan;

class TrimCycle
{
    /**
     * Enxuga o ciclo às primeiras `$keep` cadeiras, pausando o resto —
     * o nivelamento do §4.
     *
     * Um ciclo inchado é o que faz a volta demorar tanto que a matéria do
     * começo já foi esquecida quando chega a vez dela de novo. A saída da
     * spec não é apagar disciplina: é reduzir temporariamente o número
     * delas para dar um mergulho nas mais importantes, e depois devolver.
     * Daí pausar, e daí a ordem do rodízio decidir quem fica — quem já
     * ordenou a fila por importância não precisa dizer de novo.
     *
     * Cadeiras já pausadas não são reativadas: enxugar só tira.
     */
    public function handle(StudyPlan $plan, int $keep): void
    {
        // Quem fica sai da própria fila do rodízio, que já está ordenada
        // por posição — o resto é o complemento, e não uma segunda leitura
        // que poderia discordar da primeira.
        $keeping = $plan->cycleSlots()
            ->active()
            ->take(max(0, $keep))
            ->pluck('cycle_slots.id');

        $plan->cycleSlots()
            ->active()
            ->whereNotIn('cycle_slots.id', $keeping)
            ->update(['state' => CycleSlotState::Paused]);
    }
}
