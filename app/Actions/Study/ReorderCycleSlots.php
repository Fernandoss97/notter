<?php

namespace App\Actions\Study;

use App\Models\CycleSlot;
use App\Models\StudyPlan;
use Illuminate\Support\Facades\DB;

class ReorderCycleSlots
{
    /**
     * Renumera as cadeiras do rodízio na ordem recebida.
     *
     * Ids que não pertencem ao plano são ignorados em silêncio, e cadeiras
     * não citadas ficam no fim preservando a ordem anterior — a tela manda
     * a lista inteira, mas uma cadeira criada em outra aba entre o
     * carregamento e o salvamento não deve sumir do ciclo por isso.
     *
     * Reordenar no meio de uma volta é permitido e não move tempo nenhum:
     * cada bloco guarda o próprio acumulado, então a nova ordem só decide
     * qual é o *próximo*.
     *
     * @param  array<int, int>  $orderedIds
     */
    public function handle(StudyPlan $plan, array $orderedIds): void
    {
        $slots = $plan->cycleSlots()->get()->keyBy('id');

        $ordered = collect($orderedIds)
            ->unique()
            ->filter(fn (int $id) => $slots->has($id))
            ->values();

        $rest = $slots->keys()->reject(fn (int $id) => $ordered->contains($id));

        DB::transaction(function () use ($ordered, $rest, $slots): void {
            $ordered->concat($rest)->each(function (int $id, int $position) use ($slots): void {
                $slot = $slots->get($id);

                if ($slot instanceof CycleSlot && $slot->position !== $position) {
                    $slot->update(['position' => $position]);
                }
            });
        });
    }
}
