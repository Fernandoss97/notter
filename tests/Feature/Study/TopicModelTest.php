<?php

use App\Models\Review;
use App\Models\Topic;

test('a topic without a pending review has no review status', function () {
    $topic = Topic::factory()->create();
    $topic->load('reviews');

    expect($topic->reviewStatus())->toBeNull();
});

test('a topic with an overdue pending review reports overdue', function () {
    $topic = Topic::factory()->create();
    Review::factory()->for($topic)->overdue()->create();
    $topic->load('reviews');

    expect($topic->reviewStatus())->toBe('overdue');
});

test('a topic with a future pending review reports ontrack', function () {
    $topic = Topic::factory()->create();
    Review::factory()->for($topic)->create(['due_on' => now()->addDays(3)->toDateString()]);
    $topic->load('reviews');

    expect($topic->reviewStatus())->toBe('ontrack');
});

test('pendingReview ignores completed reviews and returns the latest sequence', function () {
    $topic = Topic::factory()->create();
    Review::factory()->for($topic)->completed()->create(['sequence' => 1]);
    $pending = Review::factory()->for($topic)->create(['sequence' => 2, 'due_on' => now()->toDateString()]);
    $topic->load('reviews');

    expect($topic->pendingReview()?->is($pending))->toBeTrue();
});

test('isCompleted reflects completed_at', function () {
    $incomplete = Topic::factory()->create();
    $complete = Topic::factory()->completed()->create();

    expect($incomplete->isCompleted())->toBeFalse();
    expect($complete->isCompleted())->toBeTrue();
});
