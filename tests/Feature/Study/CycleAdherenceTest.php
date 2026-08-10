<?php

use App\Models\CycleBlock;
use App\Models\CycleSlot;
use App\Models\StudyPlan;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\User;

/**
 * Um plano com rodízio de duas disciplinas nas proporções dadas.
 *
 * @return array{0: User, 1: StudyPlan, 2: Subject, 3: Subject}
 */
function planWithCycleShares(int $firstMinutes, int $secondMinutes): array
{
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();

    $first = Subject::factory()->for($plan)->create(['name' => 'Direito']);
    $second = Subject::factory()->for($plan)->create(['name' => 'Português']);

    foreach ([[$first, $firstMinutes], [$second, $secondMinutes]] as $position => [$subject, $minutes]) {
        CycleSlot::factory()->create([
            'study_plan_id' => $plan->id,
            'subject_id' => $subject->id,
            'position' => $position,
            'block_minutes' => $minutes,
        ]);
    }

    return [$user, $plan, $first, $second];
}

function studied(StudyPlan $plan, Subject $subject, int $minutes): void
{
    StudySession::factory()->completed()->create([
        'user_id' => $plan->user_id,
        'study_plan_id' => $plan->id,
        'subject_id' => $subject->id,
        'accumulated_seconds' => $minutes * 60,
        'ended_at' => now()->subDay(),
    ]);
}

test('adherence compares shares, not absolute minutes', function () {
    [$user, $plan, $first, $second] = planWithCycleShares(60, 60);

    // Metade do planejado, mas na proporção certa: o rodízio foi cumprido.
    studied($plan, $first, 30);
    studied($plan, $second, 30);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('cycle.rows.0.targetPercent', 50)
            ->where('cycle.rows.0.actualPercent', 50)
            ->where('cycle.rows.0.drift', 0)
            ->where('cycle.rows.1.drift', 0));
});

test('adherence exposes the subject getting less time than it asked for', function () {
    [$user, $plan, $first, $second] = planWithCycleShares(60, 60);

    studied($plan, $first, 180);
    studied($plan, $second, 20);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('cycle.rows.1.name', 'Português')
            ->where('cycle.rows.1.actualPercent', 10)
            ->where('cycle.rows.1.drift', -40));
});

test('a longer block asks for a bigger share', function () {
    [$user, $plan] = planWithCycleShares(90, 30);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('cycle.rows.0.targetPercent', 75)
            ->where('cycle.rows.1.targetPercent', 25));
});

test('time older than the window does not count', function () {
    [$user, $plan, $first] = planWithCycleShares(60, 60);

    StudySession::factory()->completed()->create([
        'user_id' => $user->id,
        'study_plan_id' => $plan->id,
        'subject_id' => $first->id,
        'accumulated_seconds' => 600 * 60,
        'ended_at' => now()->subMonths(3),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('cycle.rows.0.minutes', 0));
});

test('a lap only counts as closed when no seat left a block open', function () {
    [$user, $plan] = planWithCycleShares(60, 60);
    $slots = $plan->cycleSlots()->get();

    // Volta 1 inteira fechada; na volta 2, uma cadeira ainda deve.
    foreach ($slots as $slot) {
        CycleBlock::factory()->for($slot)->completed()->create(['lap' => 1]);
    }
    CycleBlock::factory()->for($slots[0])->completed()->create(['lap' => 2]);
    CycleBlock::factory()->for($slots[1])->create(['lap' => 2]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('cycle.closedLaps', 1)
            ->where('cycle.blocksDone', 3));
});

test('a plan without a rotation shows no adherence at all', function () {
    $user = User::factory()->create();
    StudyPlan::factory()->for($user)->active()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('cycle', null));
});
