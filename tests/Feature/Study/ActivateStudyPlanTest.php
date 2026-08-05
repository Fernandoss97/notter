<?php

use App\Actions\Study\ActivateStudyPlan;
use App\Models\StudyPlan;
use App\Models\User;

test('activating a plan deactivates the user other plans', function () {
    $user = User::factory()->create();
    $currentlyActive = StudyPlan::factory()->for($user)->active()->create();
    $toActivate = StudyPlan::factory()->for($user)->create(['is_active' => false]);

    (new ActivateStudyPlan)->handle($toActivate);

    expect($toActivate->refresh()->is_active)->toBeTrue();
    expect($currentlyActive->refresh()->is_active)->toBeFalse();
});

test('activating a plan does not affect other users plans', function () {
    $otherUsersActivePlan = StudyPlan::factory()->active()->create();
    $toActivate = StudyPlan::factory()->create(['is_active' => false]);

    (new ActivateStudyPlan)->handle($toActivate);

    expect($otherUsersActivePlan->refresh()->is_active)->toBeTrue();
});
