<?php

use App\Actions\Study\CompleteFocusSession;
use App\Models\Enums\ActivityType;
use App\Models\StudyPlan;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;

test('a topic starts with no pages read', function () {
    expect(Topic::factory()->create()->pages_read)->toBe(0);
});

test('pages add up on the topic across sessions', function () {
    $topic = Topic::factory()->create(['pages_read' => 12]);

    $topic->addPages(8);

    expect($topic->refresh()->pages_read)->toBe(20);
});

test('adding zero pages is a no-op', function () {
    $topic = Topic::factory()->create(['pages_read' => 5]);

    $topic->addPages(0);

    expect($topic->refresh()->pages_read)->toBe(5);
});

test('ending a session hands its pages to the topic', function () {
    $subject = Subject::factory()->create();
    $topic = Topic::factory()->for($subject)->create(['pages_read' => 4]);
    $session = StudySession::factory()->for($subject)->for($topic)->create();

    app(CompleteFocusSession::class)->handle(
        $session,
        $subject,
        $topic,
        ActivityType::Reading,
        pagesRead: 15,
    );

    expect($session->refresh()->pages_read)->toBe(15)
        ->and($topic->refresh()->pages_read)->toBe(19);
});

test('pages go nowhere when the topic was not confirmed on close', function () {
    $subject = Subject::factory()->create();
    $topic = Topic::factory()->for($subject)->create(['pages_read' => 4]);
    $session = StudySession::factory()->for($subject)->for($topic)->create();

    app(CompleteFocusSession::class)->handle($session, pagesRead: 15);

    expect($topic->refresh()->pages_read)->toBe(4);
});

test('the pages of a session reach the history', function () {
    $user = User::factory()->create();
    StudySession::factory()->for($user)->completed()->create(['pages_read' => 22]);

    $this->actingAs($user)
        ->get(route('history.index'))
        ->assertInertia(fn ($page) => $page->where('sessions.0.pages', 22));
});

test('pages can be logged by hand on a task', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $topic = Topic::factory()->for(Subject::factory()->for($plan))->create();

    $this->actingAs($user)
        ->from(route('track.index'))
        ->put(route('topics.exercises', $topic), [
            'exercises_total' => 10,
            'exercises_correct' => 8,
            'pages_read' => 30,
        ])
        ->assertRedirect(route('track.index'));

    expect($topic->refresh()->pages_read)->toBe(30);
});

test('the pages of a subject add up on the dashboard', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();
    Topic::factory()->for($subject)->create(['pages_read' => 10]);
    Topic::factory()->for($subject)->create(['pages_read' => 25]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('evolution.rows.0.pages', 35)
            ->where('evolution.totals.pages', 35));
});
