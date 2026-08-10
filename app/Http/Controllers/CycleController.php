<?php

namespace App\Http\Controllers;

use App\Actions\Study\ResolveCurrentCycleBlock;
use App\Actions\Study\ResolveCurrentFocusSession;
use App\Concerns\ListsStudyPlans;
use App\Concerns\SummarizesCycleBlock;
use App\Models\CycleBlock;
use App\Models\CycleSlot;
use App\Models\StudyPlan;
use App\Models\StudySession;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CycleController extends Controller
{
    use ListsStudyPlans;
    use SummarizesCycleBlock;

    public function __construct(
        private ResolveCurrentCycleBlock $resolveCurrentBlock,
        private ResolveCurrentFocusSession $resolveCurrentSession,
    ) {}

    /**
     * O ciclo de estudos: as disciplinas na ordem em que se alternam e a
     * volta em andamento.
     *
     * Responde "qual disciplina agora", que é pergunta diferente das
     * outras três telas de planejamento — o plano responde "como está cada
     * disciplina", a trilha "qual tarefa vem agora", e só o ciclo diz de
     * quem é a vez.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $plan = $user->activeStudyPlan()->first();

        if (! $plan instanceof StudyPlan) {
            return Inertia::render('cycle/index', [
                'plans' => $this->planOptions($request),
                'plan' => null,
                'cycle' => null,
                'availableSubjects' => [],
            ]);
        }

        $session = $this->resolveCurrentSession->handle($user);
        $slots = $plan->cycleSlots()->with('subject')->get();
        $currentBlock = $this->resolveCurrentBlock->handle($plan);

        return Inertia::render('cycle/index', [
            'plans' => $this->planOptions($request),
            'plan' => ['id' => $plan->id, 'name' => $plan->name],
            'cycle' => [
                'lap' => $currentBlock instanceof CycleBlock ? $currentBlock->lap : 0,
                'currentBlockId' => $currentBlock?->id,
                'slots' => $this->slotProps($slots, $currentBlock, $session),
                // Quantas matérias estão em primeiro contato, contra o que
                // o método recomenda (§4). É o número que dá o sinal de
                // ciclo inchado antes de a volta ficar longa demais.
                'readingCount' => $slots
                    ->filter(fn (CycleSlot $slot) => $slot->isActive() && $slot->subject->mode->isFirstContact())
                    ->count(),
                'recommendedReading' => Subject::RECOMMENDED_READING_SUBJECTS,
            ],
            'availableSubjects' => $this->availableSubjects($plan, $slots),
        ]);
    }

    /**
     * As cadeiras do rodízio com o bloco de cada uma na volta corrente.
     *
     * Cadeira pausada aparece — é o oposto de apagada, e a tela precisa
     * dela para devolvê-la ao ciclo — mas sem bloco: fora do rodízio, não
     * há vez.
     *
     * @param  Collection<int, CycleSlot>  $slots
     * @return array<int, array{id: int, subjectId: int, subjectName: string, subjectMode: string, blockMinutes: int, boosted: bool, state: string, isCurrent: bool, block: array<string, mixed>|null}>
     */
    private function slotProps(Collection $slots, ?CycleBlock $currentBlock, ?StudySession $session): array
    {
        $blocks = $currentBlock instanceof CycleBlock
            ? CycleBlock::query()
                ->whereIn('cycle_slot_id', $slots->modelKeys())
                ->where('lap', $currentBlock->lap)
                ->with('cycleSlot.subject')
                ->get()
                ->keyBy('cycle_slot_id')
            : collect();

        return $slots->map(function (CycleSlot $slot) use ($blocks, $currentBlock, $session) {
            $block = $blocks->get($slot->id);

            return [
                'id' => $slot->id,
                'subjectId' => $slot->subject_id,
                'subjectName' => $slot->subject->name,
                'subjectMode' => $slot->subject->mode->value,
                'blockMinutes' => $slot->block_minutes,
                'boosted' => $slot->isBoosted(),
                'state' => $slot->state->value,
                'isCurrent' => $block instanceof CycleBlock
                    && $currentBlock instanceof CycleBlock
                    && $block->id === $currentBlock->id,
                'block' => $this->cycleBlockProp($block, $session),
            ];
        })->all();
    }

    /**
     * Disciplinas do plano que ainda não têm cadeira. Uma disciplina ocupa
     * no máximo uma: peso maior se expressa em minutos por bloco.
     *
     * @param  Collection<int, CycleSlot>  $slots
     * @return array<int, array{id: int, name: string}>
     */
    private function availableSubjects(StudyPlan $plan, Collection $slots): array
    {
        $taken = $slots->pluck('subject_id')->all();

        return $plan->subjects()
            ->get()
            ->reject(fn (Subject $subject) => in_array($subject->id, $taken, strict: true))
            ->map(fn (Subject $subject) => ['id' => $subject->id, 'name' => $subject->name])
            ->values()
            ->all();
    }
}
