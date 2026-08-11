<?php

use App\Actions\Study\CompleteCycleBlock;
use App\Actions\Study\ResolveCurrentCycleBlock;
use App\Actions\Study\ResolveLapOrder;
use App\Concerns\SummarizesCycleAdherence;
use App\Models\CycleBlock;
use App\Models\CycleSlot;
use App\Models\StudyPlan;
use App\Models\StudySession;
use App\Models\Subject;
use Illuminate\Support\Collection;

/**
 * Um rodízio com peso: cada nome vem acompanhado das passagens que a
 * disciplina tem por volta.
 *
 * @param  array<string, int>  $weights
 * @return array{0: StudyPlan, 1: Collection<int, CycleSlot>}
 */
function weightedCycle(array $weights): array
{
    $plan = StudyPlan::factory()->active()->create();
    $position = 0;

    $slots = collect($weights)->map(function (int $blocksPerLap, string $name) use ($plan, &$position) {
        return CycleSlot::factory()->create([
            'study_plan_id' => $plan->id,
            'subject_id' => Subject::factory()->for($plan)->create(['name' => $name])->id,
            'position' => $position++,
            'block_minutes' => 60,
            'blocks_per_lap' => $blocksPerLap,
        ]);
    })->values();

    return [$plan, $slots];
}

/**
 * Os nomes das disciplinas na ordem em que a volta as chama.
 *
 * @return array<int, string>
 */
function lapQueue(StudyPlan $plan): array
{
    return (new ResolveLapOrder)
        ->handle($plan->cycleSlots()->active()->with('subject')->get())
        ->map(fn (array $passage) => $passage['slot']->subject->name)
        ->all();
}

test('a subject with two passages comes back after the others, not right away', function () {
    [$plan] = weightedCycle(['Direito' => 2, 'Português' => 1, 'Informática' => 1]);

    expect(lapQueue($plan))->toBe(['Direito', 'Português', 'Informática', 'Direito']);
});

test('the queue spreads what it can and stacks only what is left over', function () {
    [$plan] = weightedCycle(['Direito' => 3, 'Português' => 1]);

    // Não há como separar três passagens com uma só matéria no meio. O
    // rodízio distribui enquanto há com quem alternar e empilha o resto —
    // espalhar mal é melhor que empilhar tudo de uma vez.
    expect(lapQueue($plan))->toBe(['Direito', 'Português', 'Direito', 'Direito']);
});

test('a weightless cycle keeps the plain order of the seats', function () {
    [$plan] = weightedCycle(['Direito' => 1, 'Português' => 1]);

    expect(lapQueue($plan))->toBe(['Direito', 'Português']);
});

test('opening a lap gives the seat one block per passage', function () {
    [$plan, $slots] = weightedCycle(['Direito' => 2, 'Português' => 1]);

    (new ResolveCurrentCycleBlock)->handle($plan);

    expect(CycleBlock::where('lap', 1)->count())->toBe(3)
        ->and(CycleBlock::where('cycle_slot_id', $slots[0]->id)->pluck('sequence')->sort()->values()->all())
        ->toBe([1, 2]);
});

test('the turn follows the queue, and the second passage waits for the others', function () {
    [$plan, $slots] = weightedCycle(['Direito' => 2, 'Português' => 1]);
    $resolve = new ResolveCurrentCycleBlock;
    $complete = new CompleteCycleBlock;

    $first = $resolve->handle($plan);
    expect($first->cycle_slot_id)->toBe($slots[0]->id)
        ->and($first->sequence)->toBe(1);

    $complete->handle($first);
    expect($resolve->handle($plan)->cycle_slot_id)->toBe($slots[1]->id);

    $complete->handle($resolve->handle($plan));
    $third = $resolve->handle($plan);

    expect($third->cycle_slot_id)->toBe($slots[0]->id)
        ->and($third->sequence)->toBe(2)
        ->and($third->lap)->toBe(1);
});

test('the lap only closes when every passage of it closed', function () {
    [$plan] = weightedCycle(['Direito' => 2, 'Português' => 1]);
    $resolve = new ResolveCurrentCycleBlock;
    $complete = new CompleteCycleBlock;

    $complete->handle($resolve->handle($plan));
    $complete->handle($resolve->handle($plan));

    expect($resolve->handle($plan)->lap)->toBe(1);

    $complete->handle($resolve->handle($plan));

    expect($resolve->handle($plan)->lap)->toBe(2);
});

test('lowering the weight mid-lap drops the passage nobody had started', function () {
    [$plan, $slots] = weightedCycle(['Direito' => 3, 'Português' => 1]);
    $resolve = new ResolveCurrentCycleBlock;
    $resolve->handle($plan);

    expect(CycleBlock::where('lap', 1)->count())->toBe(4);

    // Sem isso a volta ficaria com blocos que ninguém mais vai estudar — e
    // volta com bloco aberto não fecha, então o rodízio pararia ali.
    $slots[0]->update(['blocks_per_lap' => 1]);
    $resolve->handle($plan);

    expect(CycleBlock::where('lap', 1)->count())->toBe(2)
        ->and(CycleBlock::where('cycle_slot_id', $slots[0]->id)->pluck('sequence')->all())
        ->toBe([1]);
});

test('lowering the weight never throws away a passage with time inside it', function () {
    [$plan, $slots] = weightedCycle(['Direito' => 2, 'Português' => 1]);
    $resolve = new ResolveCurrentCycleBlock;
    $resolve->handle($plan);

    $second = CycleBlock::where('cycle_slot_id', $slots[0]->id)->where('sequence', 2)->sole();
    StudySession::factory()->completed()->create([
        'study_plan_id' => $plan->id,
        'subject_id' => $slots[0]->subject_id,
        'cycle_block_id' => $second->id,
        'accumulated_seconds' => 15 * 60,
    ]);
    $second->update(['accumulated_seconds' => 15 * 60]);

    $slots[0]->update(['blocks_per_lap' => 1]);
    $resolve->handle($plan);

    expect(CycleBlock::whereKey($second->id)->exists())->toBeTrue()
        ->and($second->refresh()->accumulated_seconds)->toBe(15 * 60);
});

test('a lap that lost its surplus passages still closes', function () {
    [$plan, $slots] = weightedCycle(['Direito' => 3, 'Português' => 1]);
    $resolve = new ResolveCurrentCycleBlock;
    $complete = new CompleteCycleBlock;
    $resolve->handle($plan);

    $slots[0]->update(['blocks_per_lap' => 1]);

    $complete->handle($resolve->handle($plan));
    $complete->handle($resolve->handle($plan));

    expect($resolve->handle($plan)->lap)->toBe(2);
});

test('the weight raises what the cycle promises the subject', function () {
    [$plan, $slots] = weightedCycle(['Direito' => 2, 'Português' => 1]);

    $adherence = (new class
    {
        use SummarizesCycleAdherence;

        /** @return array<string, mixed>|null */
        public function of(StudyPlan $plan): ?array
        {
            return $this->cycleAdherence($plan);
        }
    })->of($plan);

    // Duas passagens de 60 contra uma: dois terços da volta são do Direito.
    expect($adherence['rows'][0]['targetPercent'])->toBe(67)
        ->and($adherence['rows'][1]['targetPercent'])->toBe(33)
        ->and($slots[0]->blocks_per_lap)->toBe(2);
});
