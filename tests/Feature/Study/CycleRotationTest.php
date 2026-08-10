<?php

use App\Actions\Study\AddCycleSlot;
use App\Actions\Study\CompleteCycleBlock;
use App\Actions\Study\Exceptions\InvalidCycleTransitionException;
use App\Actions\Study\ReorderCycleSlots;
use App\Actions\Study\ResolveCurrentCycleBlock;
use App\Actions\Study\SyncCycleBlockProgress;
use App\Models\CycleBlock;
use App\Models\CycleSlot;
use App\Models\StudyPlan;
use App\Models\StudySession;
use App\Models\Subject;
use Illuminate\Support\Collection;

/**
 * Monta um rodízio com uma cadeira por nome, na ordem recebida.
 *
 * @param  array<int, string>  $names
 * @return array{0: StudyPlan, 1: Collection<int, CycleSlot>}
 */
function cycleWith(array $names, int $blockMinutes = 60): array
{
    $plan = StudyPlan::factory()->active()->create();

    $slots = collect($names)->map(function (string $name, int $position) use ($plan, $blockMinutes) {
        $subject = Subject::factory()->for($plan)->create(['name' => $name]);

        return CycleSlot::factory()->create([
            'study_plan_id' => $plan->id,
            'subject_id' => $subject->id,
            'position' => $position,
            'block_minutes' => $blockMinutes,
        ]);
    });

    return [$plan, $slots];
}

/**
 * Registra tempo de foco no bloco pelo caminho real: uma sessão encerrada
 * apontando para ele, seguida da reconciliação.
 */
function studiedOnBlock(CycleBlock $block, int $focusSeconds): CycleBlock
{
    StudySession::factory()->completed()->create([
        'study_plan_id' => $block->cycleSlot->study_plan_id,
        'subject_id' => $block->cycleSlot->subject_id,
        'cycle_block_id' => $block->id,
        'accumulated_seconds' => $focusSeconds,
    ]);

    return (new SyncCycleBlockProgress)->handle($block);
}

test('resolving with no slots returns null', function () {
    $plan = StudyPlan::factory()->active()->create();

    expect((new ResolveCurrentCycleBlock)->handle($plan))->toBeNull();
});

test('resolving opens the first lap with one block per active slot', function () {
    [$plan, $slots] = cycleWith(['Direito', 'Português', 'Informática']);

    $block = (new ResolveCurrentCycleBlock)->handle($plan);

    expect(CycleBlock::where('lap', 1)->count())->toBe(3)
        ->and($block->cycle_slot_id)->toBe($slots[0]->id)
        ->and($block->lap)->toBe(1)
        ->and($block->target_seconds)->toBe(60 * 60);
});

test('a paused slot gets no block in the lap', function () {
    [$plan, $slots] = cycleWith(['Direito', 'Português']);
    $slots[1]->update(['state' => 'paused']);

    (new ResolveCurrentCycleBlock)->handle($plan);

    expect(CycleBlock::count())->toBe(1)
        ->and(CycleBlock::sole()->cycle_slot_id)->toBe($slots[0]->id);
});

test('resolving twice does not open the lap twice', function () {
    [$plan] = cycleWith(['Direito', 'Português']);

    (new ResolveCurrentCycleBlock)->handle($plan);
    (new ResolveCurrentCycleBlock)->handle($plan);

    expect(CycleBlock::count())->toBe(2);
});

test('two tabs opening the same lap do not collide', function () {
    [$plan, $slots] = cycleWith(['Direito', 'Português']);

    // A segunda aba lê a volta ainda vazia e tenta abri-la também. Sem
    // insertOrIgnore, o índice único derrubaria a tela principal com um 500.
    $stale = $plan->cycleSlots()->active()->get();
    (new ResolveCurrentCycleBlock)->handle($plan);

    CycleBlock::query()->insertOrIgnore(
        $stale->map(fn ($slot) => [
            'cycle_slot_id' => $slot->id,
            'lap' => 1,
            'target_seconds' => $slot->targetSeconds(),
            'accumulated_seconds' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all()
    );

    expect(CycleBlock::count())->toBe(2)
        ->and((new ResolveCurrentCycleBlock)->handle($plan)->cycle_slot_id)->toBe($slots[0]->id);
});

test('a seat pulled out mid-block keeps its time and gets it back on return', function () {
    [$plan, $slots] = cycleWith(['Direito', 'Português']);
    $resolve = new ResolveCurrentCycleBlock;
    $started = $resolve->handle($plan);
    studiedOnBlock($started, 10 * 60);

    // Fora do rodízio, a volta segue sem ela e chega ao fim.
    $slots[0]->update(['state' => 'paused']);
    (new CompleteCycleBlock)->handle($resolve->handle($plan));

    expect($resolve->handle($plan)->lap)->toBe(2);

    // De volta ao rodízio, o bloco inacabado é retomado — não descartado.
    $slots[0]->update(['state' => 'active']);
    $current = $resolve->handle($plan);

    expect($current->id)->toBe($started->id)
        ->and($current->accumulated_seconds)->toBe(10 * 60);
});

test('the rotation advances to the next slot once a block closes', function () {
    [$plan, $slots] = cycleWith(['Direito', 'Português']);
    $resolve = new ResolveCurrentCycleBlock;

    $first = $resolve->handle($plan);
    (new CompleteCycleBlock)->handle($first);

    expect($resolve->handle($plan)->cycle_slot_id)->toBe($slots[1]->id);
});

test('closing the last block of a lap opens the next one', function () {
    [$plan, $slots] = cycleWith(['Direito', 'Português']);
    $resolve = new ResolveCurrentCycleBlock;
    $complete = new CompleteCycleBlock;

    $complete->handle($resolve->handle($plan));
    $complete->handle($resolve->handle($plan));

    $block = $resolve->handle($plan);

    expect($block->lap)->toBe(2)
        ->and($block->cycle_slot_id)->toBe($slots[0]->id)
        ->and(CycleBlock::count())->toBe(4);
});

test('a started block stays current even when a slot jumps ahead of it', function () {
    [$plan, $slots] = cycleWith(['Direito', 'Português']);
    $resolve = new ResolveCurrentCycleBlock;

    $started = $resolve->handle($plan);
    studiedOnBlock($started, 300);

    (new ReorderCycleSlots)->handle($plan, [$slots[1]->id, $slots[0]->id]);

    expect($resolve->handle($plan)->id)->toBe($started->id);
});

test('a slot added mid-lap gets a block right away', function () {
    [$plan] = cycleWith(['Direito']);
    $resolve = new ResolveCurrentCycleBlock;
    $resolve->handle($plan);

    $subject = Subject::factory()->for($plan)->create(['name' => 'Português']);
    (new AddCycleSlot)->handle($plan, $subject, 90);
    $resolve->handle($plan);

    expect(CycleBlock::where('lap', 1)->count())->toBe(2);
});

test('several sessions add up on the same block across the days it takes', function () {
    [$plan] = cycleWith(['Direito'], blockMinutes: 60);
    $block = (new ResolveCurrentCycleBlock)->handle($plan);

    studiedOnBlock($block, 20 * 60);
    studiedOnBlock($block, 20 * 60);

    expect($block->refresh()->accumulated_seconds)->toBe(40 * 60)
        ->and($block->isCompleted())->toBeFalse()
        ->and($block->remainingSeconds())->toBe(20 * 60);
});

test('syncing the same block twice does not double count', function () {
    [$plan] = cycleWith(['Direito'], blockMinutes: 60);
    $block = (new ResolveCurrentCycleBlock)->handle($plan);
    studiedOnBlock($block, 25 * 60);

    (new SyncCycleBlockProgress)->handle($block);

    expect($block->refresh()->accumulated_seconds)->toBe(25 * 60);
});

test('break time never counts toward the block', function () {
    [$plan] = cycleWith(['Direito'], blockMinutes: 60);
    $block = (new ResolveCurrentCycleBlock)->handle($plan);

    StudySession::factory()->completed()->create([
        'study_plan_id' => $plan->id,
        'cycle_block_id' => $block->id,
        'accumulated_seconds' => 50 * 60,
        'break_seconds' => 10 * 60,
    ]);
    (new SyncCycleBlockProgress)->handle($block);

    expect($block->refresh()->accumulated_seconds)->toBe(50 * 60)
        ->and($block->isCompleted())->toBeFalse();
});

test('a block closes itself once the target is reached', function () {
    [$plan] = cycleWith(['Direito'], blockMinutes: 60);
    $block = (new ResolveCurrentCycleBlock)->handle($plan);

    studiedOnBlock($block, 60 * 60);

    expect($block->refresh()->isCompleted())->toBeTrue();
});

test('overshooting the target does not push progress past 100', function () {
    [$plan] = cycleWith(['Direito'], blockMinutes: 60);
    $block = (new ResolveCurrentCycleBlock)->handle($plan);

    studiedOnBlock($block, 75 * 60);

    expect($block->refresh()->progressPercent())->toBe(100);
});

test('closing a block short of the target keeps the time already spent', function () {
    [$plan] = cycleWith(['Direito'], blockMinutes: 60);
    $block = (new ResolveCurrentCycleBlock)->handle($plan);
    studiedOnBlock($block, 15 * 60);

    (new CompleteCycleBlock)->handle($block);

    expect($block->refresh()->isCompleted())->toBeTrue()
        ->and($block->accumulated_seconds)->toBe(15 * 60);
});

test('closing an already-closed block is refused', function () {
    [$plan] = cycleWith(['Direito']);
    $block = (new ResolveCurrentCycleBlock)->handle($plan);
    (new CompleteCycleBlock)->handle($block);

    expect(fn () => (new CompleteCycleBlock)->handle($block->refresh()))
        ->toThrow(InvalidCycleTransitionException::class);
});

test('changing the block duration does not rewrite a block already open', function () {
    [$plan, $slots] = cycleWith(['Direito'], blockMinutes: 60);
    $block = (new ResolveCurrentCycleBlock)->handle($plan);

    $slots[0]->update(['block_minutes' => 90]);

    expect($block->refresh()->target_seconds)->toBe(60 * 60);
});

test('a subject enters the cycle at the end of the queue', function () {
    [$plan] = cycleWith(['Direito', 'Português']);
    $subject = Subject::factory()->for($plan)->create(['name' => 'RLM']);

    $slot = (new AddCycleSlot)->handle($plan, $subject, 90);

    expect($slot->position)->toBe(2)
        ->and($slot->block_minutes)->toBe(90);
});

test('the same subject cannot take two seats in the cycle', function () {
    [$plan, $slots] = cycleWith(['Direito']);

    expect(fn () => (new AddCycleSlot)->handle($plan, $slots[0]->subject))
        ->toThrow(InvalidCycleTransitionException::class);
});

test('a subject from another plan cannot enter the cycle', function () {
    [$plan] = cycleWith(['Direito']);
    $foreign = Subject::factory()->create();

    expect(fn () => (new AddCycleSlot)->handle($plan, $foreign))
        ->toThrow(InvalidCycleTransitionException::class);
});

test('reordering renumbers the seats and keeps unlisted ones at the end', function () {
    [$plan, $slots] = cycleWith(['Direito', 'Português', 'Informática']);

    (new ReorderCycleSlots)->handle($plan, [$slots[2]->id, $slots[0]->id]);

    expect($slots[2]->refresh()->position)->toBe(0)
        ->and($slots[0]->refresh()->position)->toBe(1)
        ->and($slots[1]->refresh()->position)->toBe(2);
});

test('reordering ignores ids from another plan', function () {
    [$plan, $slots] = cycleWith(['Direito', 'Português']);
    $foreign = CycleSlot::factory()->create();

    (new ReorderCycleSlots)->handle($plan, [$foreign->id, $slots[1]->id]);

    expect($slots[1]->refresh()->position)->toBe(0)
        ->and($foreign->refresh()->position)->toBe(0);
});
