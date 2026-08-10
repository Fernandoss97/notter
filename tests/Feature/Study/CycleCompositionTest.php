<?php

use App\Actions\Study\SuggestCycleOrder;
use App\Models\CycleSlot;
use App\Models\Enums\SubjectArea;
use App\Models\StudyPlan;
use App\Models\Subject;
use App\Models\User;

/**
 * Monta um rodízio a partir de pares nome => área, na ordem recebida.
 *
 * @param  array<string, SubjectArea>  $subjects
 */
function cycleWithAreas(array $subjects, ?User $user = null): StudyPlan
{
    $plan = StudyPlan::factory()->for($user ?? User::factory())->active()->create();
    $position = 0;

    foreach ($subjects as $name => $area) {
        CycleSlot::factory()->create([
            'study_plan_id' => $plan->id,
            'subject_id' => Subject::factory()->for($plan)->create([
                'name' => $name,
                'area' => $area,
            ])->id,
            'position' => $position++,
        ]);
    }

    return $plan;
}

/**
 * @return array<int, string>
 */
function cycleOrder(StudyPlan $plan): array
{
    return $plan->cycleSlots()->with('subject')->get()
        ->map(fn (CycleSlot $slot) => $slot->subject->name)
        ->all();
}

test('interleaving separates subjects of the same family', function () {
    $plan = cycleWithAreas([
        'Constitucional' => SubjectArea::Law,
        'Administrativo' => SubjectArea::Law,
        'Português' => SubjectArea::Languages,
        'RLM' => SubjectArea::Exact,
    ]);

    (new SuggestCycleOrder)->handle($plan);

    $order = cycleOrder($plan);
    $areas = $plan->cycleSlots()->with('subject')->get()
        ->map(fn (CycleSlot $slot) => $slot->subject->area);

    expect($order)->toHaveCount(4);

    for ($i = 1; $i < $areas->count(); $i++) {
        expect($areas[$i])->not->toBe($areas[$i - 1]);
    }
});

test('the biggest family goes first, so it does not pile up at the end', function () {
    $plan = cycleWithAreas([
        'Português' => SubjectArea::Languages,
        'Constitucional' => SubjectArea::Law,
        'Administrativo' => SubjectArea::Law,
        'Penal' => SubjectArea::Law,
    ]);

    (new SuggestCycleOrder)->handle($plan);

    expect(cycleOrder($plan)[0])->toBe('Constitucional');
});

test('when there is no alternative the repetition goes in anyway', function () {
    $plan = cycleWithAreas([
        'Constitucional' => SubjectArea::Law,
        'Administrativo' => SubjectArea::Law,
        'Penal' => SubjectArea::Law,
    ]);

    (new SuggestCycleOrder)->handle($plan);

    // Uma sugestão que falhasse seria pior que uma sugestão imperfeita.
    expect(cycleOrder($plan))->toHaveCount(3);
});

test('unlabelled subjects are never treated as a family', function () {
    $plan = cycleWithAreas([
        'Uma' => SubjectArea::Other,
        'Outra' => SubjectArea::Other,
        'Português' => SubjectArea::Languages,
    ]);

    (new SuggestCycleOrder)->handle($plan);

    expect(cycleOrder($plan))->toHaveCount(3);
});

test('seats out of the rotation go to the end, keeping their order', function () {
    $plan = cycleWithAreas([
        'Constitucional' => SubjectArea::Law,
        'Português' => SubjectArea::Languages,
    ]);
    $paused = CycleSlot::factory()->paused()->create([
        'study_plan_id' => $plan->id,
        'subject_id' => Subject::factory()->for($plan)->create(['name' => 'Fora'])->id,
        'position' => 99,
    ]);

    (new SuggestCycleOrder)->handle($plan);

    expect(cycleOrder($plan))->toBe(['Constitucional', 'Português', 'Fora'])
        ->and($paused->refresh()->position)->toBe(2);
});

test('interleaving a rotation of one changes nothing', function () {
    $plan = cycleWithAreas(['Constitucional' => SubjectArea::Law]);

    (new SuggestCycleOrder)->handle($plan);

    expect(cycleOrder($plan))->toBe(['Constitucional']);
});

test('the core suggestion follows the weekly load', function (int $weeklyMinutes, int $expected) {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create([
        'weekly_goal_minutes' => $weeklyMinutes,
    ]);
    CycleSlot::factory()->create([
        'study_plan_id' => $plan->id,
        'subject_id' => Subject::factory()->for($plan)->create()->id,
    ]);

    $this->actingAs($user)
        ->get(route('cycle.index'))
        ->assertInertia(fn ($page) => $page->where('cycle.recommendedCore', $expected));
})->with([
    'menos de 20h' => [600, 4],
    'quase 20h' => [1140, 4],
    // O padrão do app cai exatamente na faixa que a spec deixou em aberto.
    '20h cravadas' => [1200, 5],
    'faixa do meio' => [1500, 5],
    'quase 30h' => [1740, 5],
    '30h' => [1800, 6],
    'muito acima' => [2400, 6],
]);

test('the rotation can be interleaved from the cycle screen', function () {
    $user = User::factory()->create();
    $plan = cycleWithAreas([
        'Constitucional' => SubjectArea::Law,
        'Administrativo' => SubjectArea::Law,
        'Português' => SubjectArea::Languages,
    ], $user);

    $this->actingAs($user)->from(route('cycle.index'))
        ->post(route('cycle-slots.interleave'))
        ->assertRedirect(route('cycle.index'));

    expect(cycleOrder($plan)[1])->toBe('Português');
});

test('the area of a subject can be changed', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();

    $this->actingAs($user)->from(route('cycle.index'))
        ->put(route('subjects.update', $subject), ['area' => 'exact'])
        ->assertRedirect(route('cycle.index'));

    expect($subject->refresh()->area)->toBe(SubjectArea::Exact);
});

test('a subject starts without a family', function () {
    expect(Subject::factory()->create()->area)->toBe(SubjectArea::Other);
});
