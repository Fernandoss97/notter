<?php

namespace App\Actions\Study;

use App\Models\CycleSlot;
use App\Models\Enums\SubjectArea;
use App\Models\StudyPlan;
use Illuminate\Support\Collection;

class SuggestCycleOrder
{
    public function __construct(private ReorderCycleSlots $reorder) {}

    /**
     * Reordena o rodízio intercalando as famílias de disciplina (§3).
     *
     * Dois direitos seguidos, ou duas de exatas, cansam a mesma parte da
     * cabeça — a ideia do ciclo é justamente trocar de estímulo. O
     * algoritmo é o guloso de sempre: a cada passo escolhe a família com
     * mais matérias por colocar, desde que não seja a que acabou de sair.
     *
     * Ele não garante zero repetição, e não deveria: com quatro direitos e
     * um português, alguma dupla vai acabar junta. Quando não há
     * alternativa, a repetição entra em vez de a sugestão falhar — é uma
     * sugestão, não uma restrição, e o usuário pode reordenar por cima.
     *
     * Cadeiras pausadas ficam no fim, na ordem que tinham: elas não estão
     * no rodízio, e intercalar quem não participa só embaralharia a volta
     * seguinte de quem voltar.
     */
    public function handle(StudyPlan $plan): void
    {
        $slots = $plan->cycleSlots()->with('subject')->get();
        $active = $slots->filter(fn (CycleSlot $slot) => $slot->isActive());

        if ($active->count() < 2) {
            return;
        }

        $ordered = $this->interleave($active);
        $paused = $slots->reject(fn (CycleSlot $slot) => $slot->isActive());

        $this->reorder->handle(
            $plan,
            $ordered->concat($paused)->map(fn (CycleSlot $slot) => $slot->id)->all(),
        );
    }

    /**
     * @param  Collection<int, CycleSlot>  $slots
     * @return Collection<int, CycleSlot>
     */
    private function interleave(Collection $slots): Collection
    {
        /** @var Collection<string, Collection<int, CycleSlot>> $byArea */
        $byArea = $slots->groupBy(fn (CycleSlot $slot) => $slot->subject->area->value);

        $ordered = collect();
        $lastArea = null;

        while ($byArea->flatten()->isNotEmpty()) {
            $area = $this->nextArea($byArea, $lastArea);
            $group = $byArea->get($area);

            $ordered->push($group->shift());
            $lastArea = $area;

            if ($group->isEmpty()) {
                $byArea->forget($area);
            }
        }

        return $ordered->values();
    }

    /**
     * A família mais numerosa entre as que ainda têm matéria, evitando a
     * que acabou de sair. Mais numerosa primeiro porque é ela que corre o
     * risco de sobrar toda no fim, empilhada — é a mesma razão pela qual
     * se distribui a maior pilha antes.
     *
     * @param  Collection<string, Collection<int, CycleSlot>>  $byArea
     */
    private function nextArea(Collection $byArea, ?string $lastArea): string
    {
        $candidates = $byArea->reject(
            fn (Collection $group, string $area) => $lastArea !== null
                && SubjectArea::from($area)->clashesWith(SubjectArea::from($lastArea))
        );

        // Só sobrou a família anterior: repetir é melhor que parar.
        if ($candidates->isEmpty()) {
            $candidates = $byArea;
        }

        return (string) $candidates
            ->sortByDesc(fn (Collection $group) => $group->count())
            ->keys()
            ->first();
    }
}
