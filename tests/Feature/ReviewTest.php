<?php

use App\Models\Review;
use App\Models\StudyPlan;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;

/**
 * Cria uma revisão pendente pertencente ao usuário informado.
 */
function pendingReviewFor(User $user, string $dueOn = '-1 day', int $sequence = 1): Review
{
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();
    $topic = Topic::factory()->for($subject)->completed()->create();

    return Review::factory()->for($topic)->create([
        'sequence' => $sequence,
        'due_on' => now()->parse($dueOn)->toDateString(),
    ]);
}

test('guests cannot complete a review', function () {
    $review = pendingReviewFor(User::factory()->create());

    $this->post("/revisoes/{$review->id}/complete")->assertRedirect('/login');
});

test('a user can complete a due review and the next one is scheduled', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $review = pendingReviewFor($user);

    $this->actingAs($user)
        ->post("/revisoes/{$review->id}/complete")
        ->assertRedirect();

    expect($review->refresh()->completed_at)->not->toBeNull()
        ->and($review->topic->reviews()->where('sequence', 2)->exists())->toBeTrue();
});

test('a user cannot complete a review that belongs to someone else', function () {
    $user = User::factory()->create();
    $review = pendingReviewFor(User::factory()->create());

    $this->actingAs($user)
        ->post("/revisoes/{$review->id}/complete")
        ->assertForbidden();

    expect($review->refresh()->completed_at)->toBeNull();
});

test('the focus screen lists the due reviews with the most overdue first', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create(['name' => 'Direito Constitucional']);
    $topic = Topic::factory()->for($subject)->completed()->create(['name' => 'Princípios']);

    Review::factory()->for($topic)->create(['sequence' => 1, 'due_on' => today()->subDays(3)]);
    Review::factory()->for($topic)->create(['sequence' => 2, 'due_on' => today()]);

    $this->actingAs($user)
        ->get('/foco')
        ->assertInertia(fn ($page) => $page
            ->has('dueReviews', 2)
            ->where('dueReviews.0.subjectName', 'Direito Constitucional')
            ->where('dueReviews.0.topicName', 'Princípios')
            ->where('dueReviews.0.overdue', true)
            ->where('dueReviews.1.overdue', false));
});

test('the focus screen hides reviews that are not due yet or already done', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();
    $topic = Topic::factory()->for($subject)->completed()->create();

    Review::factory()->for($topic)->create(['sequence' => 1, 'due_on' => today()->addDays(3)]);
    Review::factory()->for($topic)->completed()->create(['sequence' => 2, 'due_on' => today()->subDay()]);

    $this->actingAs($user)
        ->get('/foco')
        ->assertInertia(fn ($page) => $page->has('dueReviews', 0));
});

test('the focus screen does not leak reviews from other users', function () {
    $user = User::factory()->create();
    StudyPlan::factory()->for($user)->active()->create();
    pendingReviewFor(User::factory()->create());

    $this->actingAs($user)
        ->get('/foco')
        ->assertInertia(fn ($page) => $page->has('dueReviews', 0));
});
