<?php

namespace App\Http\Controllers;

use App\Actions\Study\ResolveCurrentCycleBlock;
use App\Actions\Study\ResolveCurrentFocusSession;
use App\Actions\Study\ResolveLapOrder;
use App\Concerns\ListsStudyPlans;
use App\Concerns\SuggestsCoreSubjects;
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
    use SuggestsCoreSubjects;
    use SummarizesCycleBlock;

    public function __construct(
        private ResolveCurrentCycleBlock $resolveCurrentBlock,
        private ResolveCurrentFocusSession $resolveCurrentSession,
        private ResolveLapOrder $lapOrder,
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
                'lapOrder' => $this->lapOrderProps($slots),
                // Quantas matérias estão em primeiro contato, contra o que
                // o método recomenda (§4). É o número que dá o sinal de
                // ciclo inchado antes de a volta ficar longa demais.
                'readingCount' => $slots
                    ->filter(fn (CycleSlot $slot) => $slot->isActive() && $slot->subject->mode->isFirstContact())
                    ->count(),
                'recommendedReading' => Subject::RECOMMENDED_READING_SUBJECTS,
                // O núcleo básico sugerido pela carga horária do plano
                // (§2). Sugestão, não limite — o app não impede nada.
                'recommendedCore' => $this->recommendedCoreSubjects($plan),
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
     * Com peso maior que 1 a cadeira tem várias passagens na mesma volta, e
     * a linha mostra a que está valendo: a primeira ainda aberta, ou a
     * última quando todas fecharam. É a passagem que responde "quanto falta
     * agora" — as outras a linha resume em "passagem 2 de 3".
     *
     * @param  Collection<int, CycleSlot>  $slots
     * @return array<int, array{id: int, subjectId: int, subjectName: string, subjectMode: string, subjectArea: string, blockMinutes: int, effectiveBlockMinutes: int, blocksPerLap: int, blocksDoneInLap: int, currentPassage: int, boosted: bool, state: string, isCurrent: bool, block: array<string, mixed>|null}>
     */
    private function slotProps(Collection $slots, ?CycleBlock $currentBlock, ?StudySession $session): array
    {
        $blocks = $currentBlock instanceof CycleBlock
            ? CycleBlock::query()
                ->whereIn('cycle_slot_id', $slots->modelKeys())
                ->where('lap', $currentBlock->lap)
                ->with('cycleSlot.subject')
                ->get()
                ->sortBy('sequence')
                ->groupBy('cycle_slot_id')
            : collect();

        return $slots->map(function (CycleSlot $slot) use ($blocks, $currentBlock, $session) {
            /** @var Collection<int, CycleBlock> $lapBlocks */
            $lapBlocks = $blocks->get($slot->id) ?? collect();
            $block = $lapBlocks->first(fn (CycleBlock $candidate) => ! $candidate->isCompleted())
                ?? $lapBlocks->last();

            return [
                'id' => $slot->id,
                'subjectId' => $slot->subject_id,
                'subjectName' => $slot->subject->name,
                'subjectMode' => $slot->subject->mode->value,
                'subjectArea' => $slot->subject->area->value,
                'blockMinutes' => $slot->block_minutes,
                'blocksPerLap' => $slot->blocks_per_lap,
                'blocksDoneInLap' => $lapBlocks
                    ->filter(fn (CycleBlock $candidate) => $candidate->isCompleted())
                    ->count(),
                'currentPassage' => $block instanceof CycleBlock ? $block->sequence : 0,
                // O que o bloco vale hoje, já com o reforço somado: é esse
                // o número que a linha precisa mostrar, senão a imersão
                // seria um selo sem efeito visível.
                'effectiveBlockMinutes' => $slot->effectiveBlockMinutes(),
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
     * A fila da volta: cada passagem, na ordem em que acontece.
     *
     * A tela precisa dela para o aviso de áreas vizinhas, que com pesos
     * deixou de ser a ordem das cadeiras — entre duas passagens de uma
     * mesma matéria entram outras. A fila é calculada aqui, e não repetida
     * em TypeScript, porque é ela que decide de quem é a vez: front e back
     * discordando sobre isso seria a tela mentindo.
     *
     * @param  Collection<int, CycleSlot>  $slots
     * @return array<int, array{slotId: int, sequence: int, subjectName: string, subjectArea: string}>
     */
    private function lapOrderProps(Collection $slots): array
    {
        $active = $slots->filter(fn (CycleSlot $slot) => $slot->isActive());

        return $this->lapOrder->handle($active)
            ->map(fn (array $passage) => [
                'slotId' => $passage['slot']->id,
                'sequence' => $passage['sequence'],
                'subjectName' => $passage['slot']->subject->name,
                'subjectArea' => $passage['slot']->subject->area->value,
            ])
            ->all();
    }

    /**
     * Disciplinas do plano que ainda não têm cadeira. Uma disciplina ocupa
     * no máximo uma: peso maior se expressa em passagens e minutos.
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
