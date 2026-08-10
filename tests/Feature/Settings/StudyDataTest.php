<?php

use App\Models\CycleBlock;
use App\Models\CycleSlot;
use App\Models\PomodoroSetting;
use App\Models\Review;
use App\Models\StudyPlan;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;

test('guests cannot delete study data', function () {
    $this->delete(route('study-data.destroy'), ['password' => 'password'])
        ->assertRedirect(route('login'));
});

test('the study data of the user is deleted, and only it', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();
    $topic = Topic::factory()->for($subject)->completed()->create();
    Review::factory()->for($topic)->create();
    // O tópico vai explícito: sem ele, a factory fabrica um por conta
    // própria — e com ele um plano de outro usuário, que sujaria a
    // contagem global logo abaixo.
    StudySession::factory()->for($user)->for($plan)->for($subject)->for($topic)->completed()->create();
    $settings = PomodoroSetting::factory()->for($user)->create(['focus_minutes' => 42]);

    // O ciclo pendura no plano, então cai na mesma cascata. Vai explícito
    // porque nada no controller o menciona: quem apagar o plano de outro
    // jeito precisa descobrir aqui que o rodízio ia junto.
    $slot = CycleSlot::factory()->create([
        'study_plan_id' => $plan->id,
        'subject_id' => $subject->id,
    ]);
    CycleBlock::factory()->for($slot)->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('study-data.destroy'), ['password' => 'password'])
        ->assertValid()
        ->assertRedirect(route('profile.edit'));

    expect(StudyPlan::count())->toBe(0)
        ->and(Subject::count())->toBe(0)
        ->and(Topic::count())->toBe(0)
        ->and(Review::count())->toBe(0)
        ->and(StudySession::count())->toBe(0)
        ->and(CycleSlot::count())->toBe(0)
        ->and(CycleBlock::count())->toBe(0);

    // Quem estudou continua existindo, autenticado e com suas preferências.
    $this->assertAuthenticatedAs($user);
    expect(User::whereKey($user->id)->exists())->toBeTrue()
        ->and($settings->refresh()->focus_minutes)->toBe(42);
});

test('the data of other users is untouched', function () {
    $user = User::factory()->create();
    StudyPlan::factory()->for($user)->active()->create();
    $stranger = User::factory()->create();
    $strangerPlan = StudyPlan::factory()->for($stranger)->active()->create();
    Topic::factory()->for(Subject::factory()->for($strangerPlan))->create();

    $this->actingAs($user)
        ->delete(route('study-data.destroy'), ['password' => 'password']);

    expect($stranger->studyPlans()->count())->toBe(1)
        ->and(Topic::count())->toBe(1);
});

test('a wrong password deletes nothing', function () {
    $user = User::factory()->create();
    StudyPlan::factory()->for($user)->active()->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('study-data.destroy'), ['password' => 'senha-errada'])
        ->assertInvalid(['password']);

    expect($user->studyPlans()->count())->toBe(1);
});

test('deleting study data twice is harmless', function () {
    $user = User::factory()->create();
    StudyPlan::factory()->for($user)->active()->create();

    $this->actingAs($user)->delete(route('study-data.destroy'), ['password' => 'password']);
    $this->actingAs($user)
        ->delete(route('study-data.destroy'), ['password' => 'password'])
        ->assertRedirect(route('profile.edit'));

    expect($user->studyPlans()->count())->toBe(0);
});

test('the focus screen survives having no plan at all afterwards', function () {
    $user = User::factory()->create();
    StudyPlan::factory()->for($user)->active()->create();

    $this->actingAs($user)->delete(route('study-data.destroy'), ['password' => 'password']);

    // A tela de foco é a porta de entrada do app: depois de apagar tudo,
    // ela precisa abrir vazia em vez de quebrar.
    $this->actingAs($user)
        ->get(route('focus.show'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('plan', null)->where('plans', []));
});
