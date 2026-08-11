<?php

use App\Models\CycleBlock;
use App\Models\CycleSlot;
use App\Models\Enums\CycleSlotState;
use App\Models\Enums\SubjectMode;
use App\Models\StudyPlan;
use App\Models\Subject;
use App\Models\User;
use Inertia\Support\SessionKey;

test('the cycle screen renders the rotation and the current lap', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $direito = Subject::factory()->for($plan)->create(['name' => 'Direito']);
    $portugues = Subject::factory()->for($plan)->create(['name' => 'Português']);
    Subject::factory()->for($plan)->create(['name' => 'RLM']);

    CycleSlot::factory()->create(['study_plan_id' => $plan->id, 'subject_id' => $direito->id, 'position' => 0, 'block_minutes' => 90]);
    CycleSlot::factory()->create(['study_plan_id' => $plan->id, 'subject_id' => $portugues->id, 'position' => 1]);

    $this->actingAs($user)
        ->get(route('cycle.index'))
        ->assertInertia(fn ($page) => $page
            ->component('cycle/index')
            ->where('cycle.lap', 1)
            ->has('cycle.slots', 2)
            ->where('cycle.slots.0.subjectName', 'Direito')
            ->where('cycle.slots.0.blockMinutes', 90)
            ->where('cycle.slots.0.isCurrent', true)
            ->where('cycle.slots.1.isCurrent', false)
            ->has('availableSubjects', 1)
            ->where('availableSubjects.0.name', 'RLM'));
});

test('the cycle screen survives a user with no plan', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('cycle.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('cycle', null)->where('plan', null));
});

test('a subject can be put in the rotation', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();

    $this->actingAs($user)
        ->from(route('cycle.index'))
        ->post(route('cycle-slots.store'), ['subject_id' => $subject->id, 'block_minutes' => 90])
        ->assertRedirect(route('cycle.index'));

    expect($plan->cycleSlots()->sole()->block_minutes)->toBe(90);
});

test('a subject from another plan is refused', function () {
    $user = User::factory()->create();
    StudyPlan::factory()->for($user)->active()->create();
    $foreign = Subject::factory()->create();

    $this->actingAs($user)
        ->from(route('cycle.index'))
        ->post(route('cycle-slots.store'), ['subject_id' => $foreign->id])
        ->assertSessionHasErrors('subject_id');
});

// A hora recomendada é conselho, não porteiro: meia hora antes do
// trabalho é um bloco legítimo, e a tela avisa em vez de recusar.
test('a block shorter than the recommended hour is accepted', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();

    $this->actingAs($user)
        ->from(route('cycle.index'))
        ->post(route('cycle-slots.store'), ['subject_id' => $subject->id, 'block_minutes' => 30])
        ->assertRedirect(route('cycle.index'));

    expect($plan->cycleSlots()->sole()->block_minutes)->toBe(30);
});

test('a block with no real length to it is refused', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();

    $this->actingAs($user)
        ->from(route('cycle.index'))
        ->post(route('cycle-slots.store'), ['subject_id' => $subject->id, 'block_minutes' => 0])
        ->assertSessionHasErrors('block_minutes');
});

test('a block longer than two hours is refused', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();

    $this->actingAs($user)
        ->from(route('cycle.index'))
        ->post(route('cycle-slots.store'), ['subject_id' => $subject->id, 'block_minutes' => 180])
        ->assertSessionHasErrors('block_minutes');
});

test('a seat can be paused and brought back without losing it', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $slot = CycleSlot::factory()->create([
        'study_plan_id' => $plan->id,
        'subject_id' => Subject::factory()->for($plan)->create()->id,
    ]);

    $this->actingAs($user)->from(route('cycle.index'))
        ->put(route('cycle-slots.update', $slot), ['state' => 'paused'])
        ->assertRedirect(route('cycle.index'));

    expect($slot->refresh()->state)->toBe(CycleSlotState::Paused);

    $this->actingAs($user)->from(route('cycle.index'))
        ->put(route('cycle-slots.update', $slot), ['state' => 'active']);

    expect($slot->refresh()->state)->toBe(CycleSlotState::Active);
});

test('a seat already in the rotation can be set to any usable length', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $slot = CycleSlot::factory()->create([
        'study_plan_id' => $plan->id,
        'subject_id' => Subject::factory()->for($plan)->create()->id,
        'block_minutes' => 60,
    ]);

    $this->actingAs($user)->from(route('cycle.index'))
        ->put(route('cycle-slots.update', $slot), ['block_minutes' => 25])
        ->assertRedirect(route('cycle.index'));

    expect($slot->refresh()->block_minutes)->toBe(25);

    $this->actingAs($user)->from(route('cycle.index'))
        ->put(route('cycle-slots.update', $slot), ['block_minutes' => 121])
        ->assertSessionHasErrors('block_minutes');

    expect($slot->refresh()->block_minutes)->toBe(25);
});

test('a seat from another user cannot be touched', function () {
    $user = User::factory()->create();
    $foreign = CycleSlot::factory()->create();

    $this->actingAs($user)
        ->put(route('cycle-slots.update', $foreign), ['block_minutes' => 90])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('cycle-slots.destroy', $foreign))
        ->assertForbidden();
});

test('removing a seat takes its blocks with it', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $slot = CycleSlot::factory()->create([
        'study_plan_id' => $plan->id,
        'subject_id' => Subject::factory()->for($plan)->create()->id,
    ]);
    CycleBlock::factory()->for($slot)->create();

    $this->actingAs($user)->from(route('cycle.index'))
        ->delete(route('cycle-slots.destroy', $slot))
        ->assertRedirect(route('cycle.index'));

    expect(CycleSlot::count())->toBe(0)
        ->and(CycleBlock::count())->toBe(0);
});

test('the rotation can be reordered', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $first = CycleSlot::factory()->create([
        'study_plan_id' => $plan->id,
        'subject_id' => Subject::factory()->for($plan)->create()->id,
        'position' => 0,
    ]);
    $second = CycleSlot::factory()->create([
        'study_plan_id' => $plan->id,
        'subject_id' => Subject::factory()->for($plan)->create()->id,
        'position' => 1,
    ]);

    $this->actingAs($user)->from(route('cycle.index'))
        ->post(route('cycle-slots.reorder'), ['slots' => [$second->id, $first->id]])
        ->assertRedirect(route('cycle.index'));

    expect($second->refresh()->position)->toBe(0)
        ->and($first->refresh()->position)->toBe(1);
});

test('a block can be called done before the target, and the next subject is announced', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $slot = CycleSlot::factory()->create([
        'study_plan_id' => $plan->id,
        'subject_id' => Subject::factory()->for($plan)->create(['name' => 'Direito'])->id,
        'position' => 0,
    ]);
    CycleSlot::factory()->create([
        'study_plan_id' => $plan->id,
        'subject_id' => Subject::factory()->for($plan)->create(['name' => 'Português'])->id,
        'position' => 1,
    ]);
    // A volta é aberta pela própria tela, não pela factory: um bloco solto
    // faria o rodízio pular para a volta 2 por falta do par, e o aviso
    // apontaria para a disciplina errada.
    $this->actingAs($user)->get(route('cycle.index'))->assertOk();

    $block = $slot->blocks()->sole();
    $block->update(['accumulated_seconds' => 900]);

    $this->actingAs($user)->from(route('cycle.index'))
        ->post(route('cycle-blocks.complete', $block))
        ->assertRedirect(route('cycle.index'))
        ->assertSessionHas(
            SessionKey::FLASH_DATA,
            fn (array $flash) => $flash['toast']['message'] === 'Bloco de Direito concluído. Agora é Português.'
        );

    // O tempo já cumprido fica: dar por encerrado não é apagar.
    expect($block->refresh()->isCompleted())->toBeTrue()
        ->and($block->accumulated_seconds)->toBe(900);
});

test('the cycle screen counts the subjects still in first contact', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();

    foreach ([SubjectMode::Reading, SubjectMode::Reading, SubjectMode::Review] as $position => $mode) {
        CycleSlot::factory()->create([
            'study_plan_id' => $plan->id,
            'subject_id' => Subject::factory()->for($plan)->create(['mode' => $mode])->id,
            'position' => $position,
        ]);
    }

    $this->actingAs($user)
        ->get(route('cycle.index'))
        ->assertInertia(fn ($page) => $page
            ->where('cycle.readingCount', 2)
            ->where('cycle.recommendedReading', Subject::RECOMMENDED_READING_SUBJECTS)
            ->where('cycle.slots.0.subjectMode', 'reading')
            ->where('cycle.slots.2.subjectMode', 'review'));
});

test('a paused seat does not count toward the first-contact load', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    CycleSlot::factory()->paused()->create([
        'study_plan_id' => $plan->id,
        'subject_id' => Subject::factory()->for($plan)->create()->id,
    ]);

    $this->actingAs($user)
        ->get(route('cycle.index'))
        ->assertInertia(fn ($page) => $page->where('cycle.readingCount', 0));
});

test('a subject mode can be changed from the cycle screen', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();
    $slot = CycleSlot::factory()->create([
        'study_plan_id' => $plan->id,
        'subject_id' => $subject->id,
        'block_minutes' => 90,
    ]);

    $this->actingAs($user)->from(route('cycle.index'))
        ->put(route('subjects.update', $subject), ['mode' => 'review'])
        ->assertRedirect(route('cycle.index'));

    expect($subject->refresh()->mode)->toBe(SubjectMode::Review)
        ->and($slot->refresh()->block_minutes)->toBe(60);
});

test('the mode of another user subject cannot be changed', function () {
    $user = User::factory()->create();
    $foreign = Subject::factory()->create();

    $this->actingAs($user)
        ->put(route('subjects.update', $foreign), ['mode' => 'done'])
        ->assertForbidden();
});

test('the cycle can be trimmed to the most important subjects', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();

    $slots = collect(range(0, 3))->map(fn (int $position) => CycleSlot::factory()->create([
        'study_plan_id' => $plan->id,
        'subject_id' => Subject::factory()->for($plan)->create()->id,
        'position' => $position,
    ]));

    $this->actingAs($user)->from(route('cycle.index'))
        ->post(route('cycle-slots.trim'), ['keep' => 2])
        ->assertRedirect(route('cycle.index'));

    expect($slots[1]->refresh()->state)->toBe(CycleSlotState::Active)
        ->and($slots[2]->refresh()->state)->toBe(CycleSlotState::Paused);
});

test('trimming below two subjects is refused', function () {
    $user = User::factory()->create();
    StudyPlan::factory()->for($user)->active()->create();

    $this->actingAs($user)->from(route('cycle.index'))
        ->post(route('cycle-slots.trim'), ['keep' => 1])
        ->assertSessionHasErrors('keep');
});

test('a subject can enter the cycle in immersion', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();

    $this->actingAs($user)->from(route('cycle.index'))
        ->post(route('cycle-slots.store'), ['subject_id' => $subject->id, 'boost' => true]);

    expect($plan->cycleSlots()->sole()->isBoosted())->toBeTrue();
});

test('a block from another user cannot be closed', function () {
    $user = User::factory()->create();
    $block = CycleBlock::factory()->create();

    $this->actingAs($user)
        ->post(route('cycle-blocks.complete', $block))
        ->assertForbidden();
});
