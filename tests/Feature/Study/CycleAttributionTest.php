<?php

use App\Actions\Study\CompleteFocusSession;
use App\Actions\Study\PauseFocusSession;
use App\Actions\Study\ResolveCurrentCycleBlock;
use App\Actions\Study\StartFocusSession;
use App\Actions\Study\SyncCycleBlockProgress;
use App\Models\CycleSlot;
use App\Models\StudyPlan;
use App\Models\Subject;
use App\Models\User;
use Inertia\Support\SessionKey;

/**
 * Um usuário com plano ativo e um rodízio de duas disciplinas.
 *
 * @return array{0: User, 1: StudyPlan, 2: Subject, 3: Subject}
 */
function userWithCycle(int $blockMinutes = 60): array
{
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();

    $first = Subject::factory()->for($plan)->create(['name' => 'Direito']);
    $second = Subject::factory()->for($plan)->create(['name' => 'Português']);

    foreach ([$first, $second] as $position => $subject) {
        CycleSlot::factory()->create([
            'study_plan_id' => $plan->id,
            'subject_id' => $subject->id,
            'position' => $position,
            'block_minutes' => $blockMinutes,
        ]);
    }

    return [$user, $plan, $first, $second];
}

test('a session started without a subject takes the one from the current block', function () {
    [$user, $plan, $first] = userWithCycle();

    $session = app(StartFocusSession::class)->handle($user, $plan);

    expect($session->subject_id)->toBe($first->id)
        ->and($session->cycle_block_id)->not->toBeNull();
});

test('a session started on the subject of the block feeds it', function () {
    [$user, $plan, $first] = userWithCycle();
    $block = (new ResolveCurrentCycleBlock)->handle($plan);

    $session = app(StartFocusSession::class)->handle($user, $plan, $first);

    expect($session->cycle_block_id)->toBe($block->id);
});

test('studying a subject out of turn does not feed the rotation', function () {
    [$user, $plan, , $second] = userWithCycle();

    $session = app(StartFocusSession::class)->handle($user, $plan, $second);

    expect($session->cycle_block_id)->toBeNull()
        ->and($session->subject_id)->toBe($second->id);
});

test('a session started without any cycle set up carries no block', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();

    $session = app(StartFocusSession::class)->handle($user, $plan);

    expect($session->cycle_block_id)->toBeNull();
});

test('pausing hands the focus time to the block', function () {
    $this->freezeTime();
    [$user, $plan] = userWithCycle();
    $block = (new ResolveCurrentCycleBlock)->handle($plan);
    $session = app(StartFocusSession::class)->handle($user, $plan);

    $this->travel(20)->minutes();
    app(PauseFocusSession::class)->handle($session);
    (new SyncCycleBlockProgress)->handle($block);

    expect($block->refresh()->accumulated_seconds)->toBe(20 * 60)
        ->and($block->isCompleted())->toBeFalse();
});

test('finishing the block advances the rotation to the next subject', function () {
    $this->freezeTime();
    [$user, $plan, , $second] = userWithCycle(blockMinutes: 60);
    $resolve = new ResolveCurrentCycleBlock;
    $block = $resolve->handle($plan);
    $session = app(StartFocusSession::class)->handle($user, $plan);

    $this->travel(60)->minutes();
    app(CompleteFocusSession::class)->handle($session);
    (new SyncCycleBlockProgress)->handle($block);

    expect($block->refresh()->isCompleted())->toBeTrue()
        ->and($resolve->handle($plan)->cycleSlot->subject_id)->toBe($second->id);
});

test('ending a session that fills the block hands the turn to the next subject', function () {
    $this->freezeTime();
    [$user, $plan] = userWithCycle(blockMinutes: 60);
    app(StartFocusSession::class)->handle($user, $plan);

    $this->travel(60)->minutes();

    $this->actingAs($user)
        ->from(route('focus.show'))
        ->post(route('focus.complete'))
        ->assertRedirect(route('focus.show'))
        // O convite para a próxima matéria é o único momento em que a
        // intercalação fala com quem estuda. Sem ele, o ciclo anda calado.
        ->assertSessionHas(
            SessionKey::FLASH_DATA,
            fn (array $flash) => str_contains($flash['toast']['message'], 'Agora é Português')
        );

    $this->actingAs($user)
        ->get(route('focus.show'))
        ->assertInertia(fn ($page) => $page
            ->where('currentBlock.subjectName', 'Português')
            ->where('currentBlock.lap', 1));
});

test('a block whose target is nearly met closes on a short session', function () {
    $this->freezeTime();
    [$user, $plan] = userWithCycle(blockMinutes: 60);
    $slot = $plan->cycleSlots()->orderBy('position')->first();
    $slot->blocks()->create(['lap' => 1, 'target_seconds' => 1]);

    app(StartFocusSession::class)->handle($user, $plan);
    $this->travel(2)->seconds();

    $this->actingAs($user)->from(route('focus.show'))->post(route('focus.complete'));

    expect($slot->blocks()->where('lap', 1)->sole()->completed_at)->not->toBeNull();
});

test('pausing short of the target leaves the turn where it was', function () {
    $this->freezeTime();
    [$user, $plan] = userWithCycle(blockMinutes: 60);
    app(StartFocusSession::class)->handle($user, $plan);

    $this->travel(20)->minutes();

    $this->actingAs($user)->from(route('focus.show'))->post(route('focus.pause'));

    $this->actingAs($user)
        ->get(route('focus.show'))
        ->assertInertia(fn ($page) => $page
            ->where('currentBlock.subjectName', 'Direito')
            ->where('currentBlock.progressPercent', 33));
});

test('the focus screen exposes the current block', function () {
    [$user, $plan] = userWithCycle(blockMinutes: 90);

    $this->actingAs($user)
        ->get(route('focus.show'))
        ->assertInertia(fn ($page) => $page
            ->where('currentBlock.subjectName', 'Direito')
            ->where('currentBlock.lap', 1)
            ->where('currentBlock.targetMinutes', 90)
            ->where('currentBlock.progressPercent', 0)
            ->where('currentBlock.isActive', false));
});

test('the focus screen shows no block when the cycle is empty', function () {
    $user = User::factory()->create();
    StudyPlan::factory()->for($user)->active()->create();

    $this->actingAs($user)
        ->get(route('focus.show'))
        ->assertInertia(fn ($page) => $page->where('currentBlock', null));
});
