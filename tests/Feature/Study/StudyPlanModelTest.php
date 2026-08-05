<?php

use App\Models\Enums\StudyPlanType;
use App\Models\StudyPlan;
use App\Models\Subject;
use App\Models\User;

test('a study plan belongs to a user', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->create();

    expect($plan->user->is($user))->toBeTrue();
});

test('subjects are returned ordered by position', function () {
    $plan = StudyPlan::factory()->create();
    $third = Subject::factory()->for($plan)->create(['position' => 2]);
    $first = Subject::factory()->for($plan)->create(['position' => 0]);
    $second = Subject::factory()->for($plan)->create(['position' => 1]);

    expect($plan->subjects->pluck('id')->all())
        ->toBe([$first->id, $second->id, $third->id]);
});

test('type casts to the StudyPlanType enum', function () {
    $plan = StudyPlan::factory()->contest()->create();

    expect($plan->type)->toBe(StudyPlanType::Contest);
});

test('a user has at most one active study plan resolved via activeStudyPlan', function () {
    $user = User::factory()->create();
    StudyPlan::factory()->for($user)->create(['is_active' => false]);
    $active = StudyPlan::factory()->for($user)->active()->create();

    expect($user->activeStudyPlan?->is($active))->toBeTrue();
});
