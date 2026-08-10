<?php

namespace App\Http\Controllers;

use App\Actions\Study\AddCycleSlot;
use App\Actions\Study\Exceptions\InvalidCycleTransitionException;
use App\Actions\Study\ReorderCycleSlots;
use App\Actions\Study\SuggestCycleOrder;
use App\Actions\Study\TrimCycle;
use App\Http\Requests\Study\ReorderCycleSlotsRequest;
use App\Http\Requests\Study\StoreCycleSlotRequest;
use App\Http\Requests\Study\TrimCycleRequest;
use App\Http\Requests\Study\UpdateCycleSlotRequest;
use App\Models\CycleSlot;
use App\Models\StudyPlan;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CycleSlotController extends Controller
{
    /**
     * Põe uma disciplina no rodízio. Como em SubjectController, não há
     * plano vindo do cliente: a cadeira é sempre do plano ativo.
     */
    public function store(StoreCycleSlotRequest $request, AddCycleSlot $action): RedirectResponse
    {
        $plan = $request->user()->activeStudyPlan;
        $subject = Subject::findOrFail($request->integer('subject_id'));

        try {
            $action->handle(
                $plan,
                $subject,
                $request->filled('block_minutes') ? $request->integer('block_minutes') : null,
                $request->boolean('boost'),
            );
        } catch (InvalidCycleTransitionException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return back();
    }

    /**
     * Ajusta a duração do bloco ou tira a disciplina do rodízio sem
     * apagá-la — o "enxugar o ciclo" da spec (§4), que precisa ser
     * reversível para servir de nivelamento temporário.
     *
     * A duração nova só vale para as próximas voltas: o bloco em aberto
     * congelou a sua no momento em que abriu.
     */
    public function update(UpdateCycleSlotRequest $request, CycleSlot $cycleSlot): RedirectResponse
    {
        $this->authorizeSlot($request, $cycleSlot);

        $cycleSlot->update($request->validated());

        return back();
    }

    /**
     * Tira a cadeira do ciclo de vez. Os blocos vão junto (cascade): eles
     * só descrevem as voltas de uma cadeira que não existe mais.
     *
     * As posições restantes não são renumeradas — a ordem é lida por
     * `position`, e buraco na numeração não muda ordem nenhuma.
     */
    public function destroy(Request $request, CycleSlot $cycleSlot): RedirectResponse
    {
        $this->authorizeSlot($request, $cycleSlot);

        $cycleSlot->delete();

        return back();
    }

    /**
     * Enxuga o ciclo às N primeiras disciplinas (§4). É o nivelamento de
     * quem deixou o rodízio crescer demais: as demais só saem de cena, e
     * voltam uma a uma quando houver fôlego.
     */
    public function trim(TrimCycleRequest $request, TrimCycle $action): RedirectResponse
    {
        $plan = $request->user()->activeStudyPlan;

        if ($plan instanceof StudyPlan) {
            $action->handle($plan, $request->integer('keep'));

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Ciclo enxugado.')]);
        }

        return back();
    }

    /**
     * Reordena o rodízio intercalando as famílias de disciplina (§3), para
     * não deixar dois direitos ou duas de exatas em sequência.
     *
     * Sobrescreve a ordem que estava lá: é um botão que o usuário aperta
     * quando quer a sugestão, e ele pode reordenar por cima depois.
     */
    public function interleave(Request $request, SuggestCycleOrder $action): RedirectResponse
    {
        $plan = $request->user()->activeStudyPlan;

        if ($plan instanceof StudyPlan) {
            $action->handle($plan);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Ordem intercalada.')]);
        }

        return back();
    }

    public function reorder(ReorderCycleSlotsRequest $request, ReorderCycleSlots $action): RedirectResponse
    {
        $plan = $request->user()->activeStudyPlan;

        if ($plan instanceof StudyPlan) {
            /** @var array<int, int> $slots */
            $slots = $request->validated('slots');

            $action->handle($plan, $slots);
        }

        return back();
    }

    private function authorizeSlot(Request $request, CycleSlot $slot): void
    {
        abort_unless($slot->studyPlan->user_id === $request->user()->id, 403);
    }
}
