<?php

use App\Models\Enums\StudyPlanType;
use App\Models\Review;
use App\Models\StudyPlan;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;

test('guests are redirected away from the plan screen', function () {
    $this->get('/plano')->assertRedirect('/login');
});

test('the plan screen renders an empty state without any plan', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/plano')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('plan/index')
            ->where('activePlan', null)
            ->where('plans', []));
});

test('the plan screen renders the active plan subjects and topics', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create(['name' => 'Concurso XYZ']);
    $subject = Subject::factory()->for($plan)->create(['name' => 'Álgebra Linear']);
    $topic = Topic::factory()->for($subject)->completed()->create(['name' => 'Espaços vetoriais']);
    Review::factory()->for($topic)->overdue()->create();

    $this->actingAs($user)
        ->get('/plano')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('activePlan.name', 'Concurso XYZ')
            ->where('activePlan.subjects.0.name', 'Álgebra Linear')
            ->where('activePlan.subjects.0.topics.0.name', 'Espaços vetoriais')
            ->where('activePlan.subjects.0.topics.0.completed', true)
            ->where('activePlan.subjects.0.topics.0.reviewStatus', 'overdue'));
});

test('creating a plan makes it active', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/plano', [
        'name' => 'Concurso XYZ',
        'type' => StudyPlanType::Contest->value,
        'weekly_goal_minutes' => 1200,
    ])->assertRedirect(route('plans.index'));

    $plan = $user->studyPlans()->sole();
    expect($plan->name)->toBe('Concurso XYZ')
        ->and($plan->is_active)->toBeTrue();
});

test('the plan list keeps a stable order regardless of which plan is active', function () {
    $user = User::factory()->create();
    StudyPlan::factory()->for($user)->create(['name' => 'Zeta']);
    StudyPlan::factory()->for($user)->active()->create(['name' => 'Alfa']);
    StudyPlan::factory()->for($user)->create(['name' => 'Beta']);

    $this->actingAs($user)
        ->get('/plano')
        ->assertInertia(fn ($page) => $page
            ->where('plans.0.name', 'Alfa')
            ->where('plans.1.name', 'Beta')
            ->where('plans.2.name', 'Zeta'));

    $zeta = $user->studyPlans()->where('name', 'Zeta')->sole();
    $this->actingAs($user)->post("/plano/{$zeta->id}/activate");

    // A troca de plano ativo não pode reordenar o seletor.
    $this->actingAs($user)
        ->get('/plano')
        ->assertInertia(fn ($page) => $page
            ->where('plans.0.name', 'Alfa')
            ->where('plans.1.name', 'Beta')
            ->where('plans.2.name', 'Zeta'));
});

test('creating a second plan deactivates the first', function () {
    $user = User::factory()->create();
    $first = StudyPlan::factory()->for($user)->active()->create();

    $this->actingAs($user)->post('/plano', [
        'name' => 'Segundo plano',
        'type' => StudyPlanType::Free->value,
        'weekly_goal_minutes' => 600,
    ]);

    expect($first->refresh()->is_active)->toBeFalse();
    expect($user->activeStudyPlan?->name)->toBe('Segundo plano');
});

test('a plan can be updated', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create([
        'name' => 'Concurso XYZ',
        'type' => StudyPlanType::Free,
        'weekly_goal_minutes' => 600,
    ]);

    $this->actingAs($user)->put("/plano/{$plan->id}", [
        'name' => 'Concurso XYZ 2027',
        'type' => StudyPlanType::Contest->value,
        'weekly_goal_minutes' => 1200,
    ])->assertRedirect(route('plans.index'));

    $plan->refresh();
    expect($plan->name)->toBe('Concurso XYZ 2027')
        ->and($plan->type)->toBe(StudyPlanType::Contest)
        ->and($plan->weekly_goal_minutes)->toBe(1200);
});

test('updating a plan requires ownership', function () {
    $user = User::factory()->create();
    $foreignPlan = StudyPlan::factory()->create();

    $this->actingAs($user)
        ->put("/plano/{$foreignPlan->id}", [
            'name' => 'Nome indevido',
            'type' => StudyPlanType::Free->value,
            'weekly_goal_minutes' => 600,
        ])
        ->assertForbidden();
});

test('activating a plan requires ownership', function () {
    $user = User::factory()->create();
    $foreignPlan = StudyPlan::factory()->create();

    $this->actingAs($user)
        ->post("/plano/{$foreignPlan->id}/activate")
        ->assertForbidden();
});

test('a subject can be added to the active plan', function () {
    $user = User::factory()->create();
    StudyPlan::factory()->for($user)->active()->create();

    $this->actingAs($user)->post('/plano/subjects', [
        'name' => 'Estatística',
        'goal_minutes' => 900,
    ])->assertRedirect(route('plans.index'));

    $subject = Subject::sole();
    expect($subject->name)->toBe('Estatística')
        ->and($subject->goal_minutes)->toBe(900);
});

test('adding a subject without an active plan flashes an error', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/plano/subjects', [
        'name' => 'Estatística',
    ]);

    expect(Subject::count())->toBe(0);
});

test('a topic can be added to a subject of the active plan', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();

    $this->actingAs($user)
        ->from(route('plans.index'))
        ->post('/plano/topics', [
            'subject_id' => $subject->id,
            'name' => 'Autovalores e autovetores',
        ])
        ->assertRedirect(route('plans.index'));

    $topic = Topic::sole();
    expect($topic->name)->toBe('Autovalores e autovetores')
        ->and($topic->subject_id)->toBe($subject->id);
});

test('a topic cannot be added to a subject outside the active plan', function () {
    $user = User::factory()->create();
    StudyPlan::factory()->for($user)->active()->create();
    $foreignSubject = Subject::factory()->create();

    $this->actingAs($user)
        ->post('/plano/topics', [
            'subject_id' => $foreignSubject->id,
            'name' => 'Tópico indevido',
        ])
        ->assertInvalid(['subject_id']);
});

test('completing a topic schedules its first review', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();
    $topic = Topic::factory()->for($subject)->create();

    $this->actingAs($user)
        ->from(route('plans.index'))
        ->post("/plano/topics/{$topic->id}/complete")
        ->assertRedirect(route('plans.index'));

    $topic->refresh();
    expect($topic->completed_at)->not->toBeNull();
    expect($topic->reviews()->count())->toBe(1);
});

test('a topic can be unmarked, taking its review schedule with it', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();
    $topic = Topic::factory()->for($subject)->create();

    $this->actingAs($user)
        ->from(route('track.index'))
        ->post("/plano/topics/{$topic->id}/complete");

    expect($topic->refresh()->completed_at)->not->toBeNull()
        ->and($topic->reviews()->count())->toBe(1);

    $this->actingAs($user)
        ->from(route('track.index'))
        ->post("/plano/topics/{$topic->id}/uncomplete")
        ->assertRedirect(route('track.index'));

    // O ciclo de revisão nasce da conclusão: desfazer a conclusão desfaz o
    // ciclo, senão a próxima conclusão criaria uma segunda revisão de
    // sequência 1 no mesmo tópico.
    expect($topic->refresh()->completed_at)->toBeNull()
        ->and($topic->reviews()->count())->toBe(0);
});

test('unmarking clears reviews already completed too', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $topic = Topic::factory()->for(Subject::factory()->for($plan))->completed()->create();
    Review::factory()->for($topic)->create(['sequence' => 1, 'completed_at' => now()]);
    Review::factory()->for($topic)->create(['sequence' => 2]);

    $this->actingAs($user)
        ->from(route('track.index'))
        ->post("/plano/topics/{$topic->id}/uncomplete");

    expect($topic->reviews()->count())->toBe(0);
});

test('unmarking a topic that was never completed changes nothing', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $topic = Topic::factory()->for(Subject::factory()->for($plan))->create();

    $this->actingAs($user)
        ->from(route('track.index'))
        ->post("/plano/topics/{$topic->id}/uncomplete")
        ->assertRedirect(route('track.index'));

    expect($topic->refresh()->completed_at)->toBeNull();
});

test('unmarking a topic requires ownership', function () {
    $user = User::factory()->create();
    $foreignTopic = Topic::factory()->completed()->create();

    $this->actingAs($user)
        ->post("/plano/topics/{$foreignTopic->id}/uncomplete")
        ->assertForbidden();

    expect($foreignTopic->refresh()->completed_at)->not->toBeNull();
});

test('completing a topic requires ownership', function () {
    $user = User::factory()->create();
    $foreignTopic = Topic::factory()->create();

    $this->actingAs($user)
        ->post("/plano/topics/{$foreignTopic->id}/complete")
        ->assertForbidden();
});
