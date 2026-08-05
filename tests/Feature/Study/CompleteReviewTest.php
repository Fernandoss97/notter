<?php

use App\Actions\Study\CompleteReview;
use App\Models\Review;
use App\Models\Topic;

test('completing a review schedules the next one in the fixed interval sequence', function () {
    $this->freezeTime();
    $topic = Topic::factory()->completed()->create();
    $review = Review::factory()->for($topic)->create(['sequence' => 1]);

    (new CompleteReview)->handle($review);

    expect($review->refresh()->completed_at)->not->toBeNull();

    $next = $topic->reviews()->where('sequence', 2)->sole();
    expect($next->due_on->toDateString())->toBe(now()->addDays(3)->toDateString());
});

test('completing the last review in the sequence does not schedule another', function () {
    $topic = Topic::factory()->completed()->create();
    $review = Review::factory()->for($topic)->create(['sequence' => 5]);

    (new CompleteReview)->handle($review);

    expect($topic->reviews()->count())->toBe(1);
});

test('completing an already-completed review is a no-op', function () {
    $topic = Topic::factory()->completed()->create();
    $review = Review::factory()->for($topic)->completed()->create(['sequence' => 1]);

    (new CompleteReview)->handle($review);

    expect($topic->reviews()->count())->toBe(1);
});
