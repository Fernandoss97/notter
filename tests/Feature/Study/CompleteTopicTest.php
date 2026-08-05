<?php

use App\Actions\Study\CompleteTopic;
use App\Models\Review;
use App\Models\Topic;

test('completing a topic sets completed_at and schedules the first review', function () {
    $this->freezeTime();
    $topic = Topic::factory()->create();

    (new CompleteTopic)->handle($topic);

    $topic->refresh();
    expect($topic->completed_at)->not->toBeNull();

    $review = $topic->reviews()->sole();
    expect($review->sequence)->toBe(1)
        ->and($review->due_on->toDateString())->toBe(now()->addDay()->toDateString())
        ->and($review->completed_at)->toBeNull();
});

test('completing an already-completed topic is a no-op', function () {
    $topic = Topic::factory()->completed()->create();
    Review::factory()->for($topic)->create();

    (new CompleteTopic)->handle($topic);

    expect($topic->reviews()->count())->toBe(1);
});
