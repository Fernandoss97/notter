<?php

use App\Actions\Study\AddCycleSlot;
use App\Actions\Study\CompleteCycleBlock;
use App\Actions\Study\ResolveCurrentCycleBlock;
use App\Actions\Study\SetSubjectMode;
use App\Actions\Study\TrimCycle;
use App\Models\CycleSlot;
use App\Models\Enums\CycleSlotState;
use App\Models\Enums\SubjectMode;
use App\Models\StudyPlan;
use App\Models\Subject;

test('a subject starts in first contact with the theory', function () {
    expect(Subject::factory()->create()->mode)->toBe(SubjectMode::Reading);
});

test('moving a subject to review shrinks its share of the rotation', function () {
    $plan = StudyPlan::factory()->active()->create();
    $subject = Subject::factory()->for($plan)->create();
    $slot = CycleSlot::factory()->create([
        'study_plan_id' => $plan->id,
        'subject_id' => $subject->id,
        'block_minutes' => 90,
        'boost_until' => today()->addWeek(),
    ]);

    (new SetSubjectMode)->handle($subject, SubjectMode::Review);

    $slot->refresh();
    expect($subject->refresh()->mode)->toBe(SubjectMode::Review)
        ->and($slot->block_minutes)->toBe(CycleSlot::DEFAULT_BLOCK_MINUTES)
        ->and($slot->boost_until)->toBeNull()
        ->and($slot->state)->toBe(CycleSlotState::Active);
});

test('ending a subject takes it out of the rotation without deleting it', function () {
    $plan = StudyPlan::factory()->active()->create();
    $subject = Subject::factory()->for($plan)->create();
    $slot = CycleSlot::factory()->create([
        'study_plan_id' => $plan->id,
        'subject_id' => $subject->id,
    ]);

    (new SetSubjectMode)->handle($subject, SubjectMode::Done);

    expect($slot->refresh()->state)->toBe(CycleSlotState::Paused)
        ->and(CycleSlot::whereKey($slot->id)->exists())->toBeTrue()
        ->and(Subject::whereKey($subject->id)->exists())->toBeTrue();
});

test('reopening the theory brings the subject back to the rotation', function () {
    $plan = StudyPlan::factory()->active()->create();
    $subject = Subject::factory()->for($plan)->create(['mode' => SubjectMode::Done]);
    $slot = CycleSlot::factory()->paused()->create([
        'study_plan_id' => $plan->id,
        'subject_id' => $subject->id,
        'block_minutes' => 60,
    ]);

    (new SetSubjectMode)->handle($subject, SubjectMode::Reading);

    // Os minutos não voltam sozinhos: quem reabre a teoria sabe melhor que
    // o sistema quanto tempo ela vai pedir agora.
    expect($slot->refresh()->state)->toBe(CycleSlotState::Active)
        ->and($slot->block_minutes)->toBe(60);
});

test('a subject with no seat in the rotation still changes mode', function () {
    $subject = Subject::factory()->create();

    (new SetSubjectMode)->handle($subject, SubjectMode::Review);

    expect($subject->refresh()->mode)->toBe(SubjectMode::Review);
});

test('a new subject can enter in immersion, with an expiry date', function () {
    $this->freezeTime();
    $plan = StudyPlan::factory()->active()->create();
    $subject = Subject::factory()->for($plan)->create();

    $slot = (new AddCycleSlot)->handle($plan, $subject, boost: true);

    // A duração escolhida não muda: o reforço é somado por cima dela.
    expect($slot->block_minutes)->toBe(CycleSlot::DEFAULT_BLOCK_MINUTES)
        ->and($slot->effectiveBlockMinutes())->toBe(
            CycleSlot::DEFAULT_BLOCK_MINUTES + CycleSlot::BOOST_EXTRA_MINUTES
        )
        ->and($slot->boost_until->toDateString())
        ->toBe(today()->addWeeks(AddCycleSlot::BOOST_WEEKS)->toDateString())
        ->and($slot->isBoosted())->toBeTrue();
});

test('the immersion holds through its last day', function () {
    $slot = CycleSlot::factory()->create([
        'block_minutes' => 60,
        'boost_until' => today(),
    ]);

    expect($slot->isBoosted())->toBeTrue()
        ->and($slot->effectiveBlockMinutes())->toBe(90);
});

test('an expired immersion stops weighing, and not just stops showing', function () {
    $slot = CycleSlot::factory()->create([
        'block_minutes' => 60,
        'boost_until' => today()->subDay(),
    ]);

    // O selo sumir sem o peso sumir junto seria a prioridade eterna de
    // volta — pior, porque invisível.
    expect($slot->isBoosted())->toBeFalse()
        ->and($slot->effectiveBlockMinutes())->toBe(60)
        ->and($slot->targetSeconds())->toBe(60 * 60);
});

test('the next lap after the immersion opens with the plain block', function () {
    $plan = StudyPlan::factory()->active()->create();
    $slot = CycleSlot::factory()->create([
        'study_plan_id' => $plan->id,
        'subject_id' => Subject::factory()->for($plan)->create()->id,
        'block_minutes' => 60,
        'boost_until' => today()->addWeek(),
    ]);
    $resolve = new ResolveCurrentCycleBlock;

    expect($resolve->handle($plan)->target_seconds)->toBe(90 * 60);

    (new CompleteCycleBlock)->handle($resolve->handle($plan));
    $slot->update(['boost_until' => today()->subDay()]);

    expect($resolve->handle($plan)->target_seconds)->toBe(60 * 60);
});

test('the immersion never pushes a block past the ceiling', function () {
    $slot = CycleSlot::factory()->create([
        'block_minutes' => CycleSlot::MAX_BLOCK_MINUTES,
        'boost_until' => today()->addWeek(),
    ]);

    expect($slot->effectiveBlockMinutes())->toBe(CycleSlot::MAX_BLOCK_MINUTES);
});

test('trimming the cycle keeps the first seats and pauses the rest', function () {
    $plan = StudyPlan::factory()->active()->create();

    $slots = collect(range(0, 4))->map(fn (int $position) => CycleSlot::factory()->create([
        'study_plan_id' => $plan->id,
        'subject_id' => Subject::factory()->for($plan)->create()->id,
        'position' => $position,
    ]));

    (new TrimCycle)->handle($plan, 2);

    expect($slots[0]->refresh()->state)->toBe(CycleSlotState::Active)
        ->and($slots[1]->refresh()->state)->toBe(CycleSlotState::Active)
        ->and($slots[2]->refresh()->state)->toBe(CycleSlotState::Paused)
        ->and($slots[4]->refresh()->state)->toBe(CycleSlotState::Paused);
});

test('trimming to more seats than there are changes nothing', function () {
    $plan = StudyPlan::factory()->active()->create();
    $slot = CycleSlot::factory()->create([
        'study_plan_id' => $plan->id,
        'subject_id' => Subject::factory()->for($plan)->create()->id,
    ]);

    (new TrimCycle)->handle($plan, 10);

    expect($slot->refresh()->state)->toBe(CycleSlotState::Active);
});

test('trimming never brings a paused seat back', function () {
    $plan = StudyPlan::factory()->active()->create();
    $paused = CycleSlot::factory()->paused()->create([
        'study_plan_id' => $plan->id,
        'subject_id' => Subject::factory()->for($plan)->create()->id,
        'position' => 0,
    ]);
    CycleSlot::factory()->create([
        'study_plan_id' => $plan->id,
        'subject_id' => Subject::factory()->for($plan)->create()->id,
        'position' => 1,
    ]);

    (new TrimCycle)->handle($plan, 2);

    expect($paused->refresh()->state)->toBe(CycleSlotState::Paused);
});
