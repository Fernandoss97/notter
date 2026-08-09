<?php

use App\Models\StudyPlan;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;

/**
 * @return array{0: User, 1: StudyPlan, 2: Subject}
 */
function planWithExam(?string $examDate): array
{
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create(['exam_date' => $examDate]);

    return [$user, $plan, Subject::factory()->for($plan)->create()];
}

test('the pace counts the days to the exam and what it demands per week', function () {
    $this->freezeTime();
    [$user, $plan, $subject] = planWithExam(today()->addDays(70)->toDateString());

    Topic::factory()->for($subject)->count(30)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pace.daysUntilExam', 70)
            ->where('pace.tasksRemaining', 30)
            ->where('pace.tasksTotal', 30)
            // 30 tarefas em 10 semanas.
            ->where('pace.requiredPerWeek', 3));
});

test('the current pace looks at the last four weeks', function () {
    $this->freezeTime();
    [$user, $plan, $subject] = planWithExam(today()->addDays(70)->toDateString());

    Topic::factory()->for($subject)->count(8)->create(['completed_at' => now()->subWeek()]);
    // Fora da janela: não conta para o ritmo, mas conta como concluída.
    Topic::factory()->for($subject)->count(4)->create(['completed_at' => now()->subMonths(3)]);
    Topic::factory()->for($subject)->count(10)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pace.currentPerWeek', 2)
            ->where('pace.tasksRemaining', 10));
});

test('a plan without an exam date demands no pace', function () {
    $this->freezeTime();
    [$user, $plan, $subject] = planWithExam(null);

    Topic::factory()->for($subject)->count(5)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pace.daysUntilExam', null)
            ->where('pace.examOn', null)
            ->where('pace.requiredPerWeek', null)
            ->where('pace.onTrack', null));
});

test('an exam already taken counts backwards and demands nothing', function () {
    $this->freezeTime();
    [$user, $plan, $subject] = planWithExam(today()->subDays(5)->toDateString());

    Topic::factory()->for($subject)->count(5)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pace.daysUntilExam', -5)
            ->where('pace.requiredPerWeek', null)
            ->where('pace.onTrack', null));
});

test('being at or above the required pace reads as on track', function () {
    $this->freezeTime();
    [$user, $plan, $subject] = planWithExam(today()->addDays(70)->toDateString());

    // 12 concluídas na janela = 3/semana; 30 restantes em 10 semanas = 3/semana.
    Topic::factory()->for($subject)->count(12)->create(['completed_at' => now()->subDays(3)]);
    Topic::factory()->for($subject)->count(30)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pace.currentPerWeek', 3)
            ->where('pace.requiredPerWeek', 3)
            ->where('pace.onTrack', true));
});

test('a standstill has no projection instead of a projection of never', function () {
    $this->freezeTime();
    [$user, $plan, $subject] = planWithExam(null);

    Topic::factory()->for($subject)->count(5)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pace.currentPerWeek', 0)
            ->where('pace.projectedOn', null));
});

test('the projection follows the current pace', function () {
    $this->freezeTime();
    [$user, $plan, $subject] = planWithExam(null);

    // 8 em quatro semanas = 2/semana; 10 restantes = 5 semanas = 35 dias.
    Topic::factory()->for($subject)->count(8)->create(['completed_at' => now()->subWeek()]);
    Topic::factory()->for($subject)->count(10)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pace.projectedOn', now()->addDays(35)->format('d/m/Y')));
});

test('a finished track has nothing left to demand', function () {
    $this->freezeTime();
    [$user, $plan, $subject] = planWithExam(today()->addDays(30)->toDateString());

    Topic::factory()->for($subject)->count(3)->create(['completed_at' => now()->subDay()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pace.tasksRemaining', 0)
            ->where('pace.requiredPerWeek', null)
            ->where('pace.projectedOn', null));
});

test('the exam date can be set and cleared on the plan', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create(['exam_date' => null]);

    $this->actingAs($user)->put("/plano/{$plan->id}", [
        'name' => $plan->name,
        'type' => $plan->type->value,
        'weekly_goal_minutes' => $plan->weekly_goal_minutes,
        'exam_date' => '2027-03-14',
    ])->assertRedirect(route('plans.index'));

    expect($plan->refresh()->exam_date->toDateString())->toBe('2027-03-14');

    $this->actingAs($user)->put("/plano/{$plan->id}", [
        'name' => $plan->name,
        'type' => $plan->type->value,
        'weekly_goal_minutes' => $plan->weekly_goal_minutes,
        'exam_date' => null,
    ]);

    expect($plan->refresh()->exam_date)->toBeNull();
});

test('an exam date in the past is accepted, so a wrong one can be fixed', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();

    $this->actingAs($user)->put("/plano/{$plan->id}", [
        'name' => $plan->name,
        'type' => $plan->type->value,
        'weekly_goal_minutes' => $plan->weekly_goal_minutes,
        'exam_date' => today()->subYear()->toDateString(),
    ])->assertValid();
});

test('the plan screen sends the exam date in the input format', function () {
    $user = User::factory()->create();
    StudyPlan::factory()->for($user)->active()->create(['exam_date' => '2027-03-14']);

    $this->actingAs($user)
        ->get('/plano')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('activePlan.examDate', '2027-03-14'));
});
