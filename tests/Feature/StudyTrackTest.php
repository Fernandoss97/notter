<?php

use App\Actions\Study\CompleteFocusSession;
use App\Models\Enums\ActivityType;
use App\Models\Enums\FocusSessionState;
use App\Models\StudyPlan;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;

test('guests are redirected away from the track screen', function () {
    $this->get('/trilha')->assertRedirect('/login');
});

test('the track screen renders an empty state without any plan', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/trilha')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('track/index')
            ->where('plan', null)
            ->where('cycles', []));
});

test('the queue crosses subjects in plan order and numbers the tasks', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $database = Subject::factory()->for($plan)->create(['name' => 'Banco de Dados']);
    $logic = Subject::factory()->for($plan)->create(['name' => 'Raciocínio Lógico']);

    Topic::factory()->for($database)->inQueue(0, 0)->create(['name' => 'Aula 00']);
    Topic::factory()->for($logic)->inQueue(0, 1)->create(['name' => 'Estruturas Lógicas I']);
    Topic::factory()->for($database)->inQueue(0, 2)->create(['name' => 'Aula 01']);

    $this->actingAs($user)
        ->get('/trilha')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('cycles', 1)
            ->where('cycles.0.cycle', 0)
            ->where('cycles.0.total', 3)
            ->where('cycles.0.tasks.0.name', 'Aula 00')
            ->where('cycles.0.tasks.0.number', 1)
            ->where('cycles.0.tasks.0.subjectName', 'Banco de Dados')
            ->where('cycles.0.tasks.1.name', 'Estruturas Lógicas I')
            ->where('cycles.0.tasks.1.number', 2)
            ->where('cycles.0.tasks.2.name', 'Aula 01')
            ->where('cycles.0.tasks.2.number', 3));
});

test('an earlier cycle comes first even when its task was added last', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();

    Topic::factory()->for($subject)->inQueue(1, 0)->create(['name' => 'Ciclo seguinte']);
    Topic::factory()->for($subject)->inQueue(0, 99)->create(['name' => 'Atrasada do ciclo 1']);

    $this->actingAs($user)
        ->get('/trilha')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('cycles', 2)
            ->where('cycles.0.cycle', 0)
            ->where('cycles.0.tasks.0.name', 'Atrasada do ciclo 1')
            ->where('cycles.0.tasks.0.number', 1)
            ->where('cycles.1.cycle', 1)
            ->where('cycles.1.tasks.0.name', 'Ciclo seguinte')
            ->where('cycles.1.tasks.0.number', 2));
});

test('a completed task reports its date and counts towards the cycle', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();

    Topic::factory()->for($subject)->inQueue(0, 0)->create([
        'completed_at' => now()->setDate(2026, 3, 14),
    ]);
    Topic::factory()->for($subject)->inQueue(0, 1)->create();

    $this->actingAs($user)
        ->get('/trilha')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('cycles.0.done', 1)
            ->where('cycles.0.total', 2)
            ->where('cycles.0.tasks.0.completed', true)
            ->where('cycles.0.tasks.0.completedOn', '14/03/2026')
            ->where('cycles.0.tasks.1.completed', false)
            ->where('cycles.0.tasks.1.completedOn', null));
});

test('the track only shows the active plan', function () {
    $user = User::factory()->create();
    $active = StudyPlan::factory()->for($user)->active()->create();
    $other = StudyPlan::factory()->for($user)->create();

    Topic::factory()->for(Subject::factory()->for($active))->create(['name' => 'Do plano ativo']);
    Topic::factory()->for(Subject::factory()->for($other))->create(['name' => 'Do outro plano']);

    $this->actingAs($user)
        ->get('/trilha')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('cycles.0.tasks', 1)
            ->where('cycles.0.tasks.0.name', 'Do plano ativo'));
});

test('a new task lands at the end of the plan queue, in the current cycle', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $first = Subject::factory()->for($plan)->create();
    $second = Subject::factory()->for($plan)->create();

    Topic::factory()->for($first)->inQueue(2, 7)->create();

    $this->actingAs($user)
        ->from(route('track.index'))
        ->post('/plano/topics', [
            'subject_id' => $second->id,
            'name' => 'Nova tarefa',
        ])
        ->assertRedirect(route('track.index'));

    $topic = Topic::where('name', 'Nova tarefa')->sole();
    expect($topic->cycle)->toBe(2)
        ->and($topic->position)->toBe(8);
});

test('the first task of an empty plan opens cycle zero at position zero', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();

    $this->actingAs($user)
        ->from(route('track.index'))
        ->post('/plano/topics', [
            'subject_id' => $subject->id,
            'name' => 'Primeira tarefa',
        ]);

    $topic = Topic::sole();
    expect($topic->cycle)->toBe(0)
        ->and($topic->position)->toBe(0);
});

test('the track reports questions per task and per cycle', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();

    Topic::factory()->for($subject)->inQueue(0, 0)->create([
        'exercises_total' => 20,
        'exercises_correct' => 15,
    ]);
    Topic::factory()->for($subject)->inQueue(0, 1)->create([
        'exercises_total' => 10,
        'exercises_correct' => 5,
    ]);

    $this->actingAs($user)
        ->get('/trilha')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('cycles.0.tasks.0.exercisesTotal', 20)
            ->where('cycles.0.tasks.0.exercisesCorrect', 15)
            ->where('cycles.0.tasks.0.accuracy', 75)
            ->where('cycles.0.exercises', 30)
            ->where('cycles.0.correct', 20)
            ->where('cycles.0.accuracy', 67));
});

test('a task without questions reports no accuracy', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    Topic::factory()->for(Subject::factory()->for($plan))->create();

    $this->actingAs($user)
        ->get('/trilha')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('cycles.0.tasks.0.exercisesTotal', 0)
            ->where('cycles.0.tasks.0.accuracy', null)
            ->where('cycles.0.accuracy', null));
});

test('questions can be recorded by hand, replacing what was there', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $topic = Topic::factory()->for(Subject::factory()->for($plan))->create([
        'exercises_total' => 10,
        'exercises_correct' => 8,
    ]);

    $this->actingAs($user)
        ->from(route('track.index'))
        ->put("/plano/topics/{$topic->id}/questoes", [
            'exercises_total' => 30,
            'exercises_correct' => 24,
        ])
        ->assertRedirect(route('track.index'));

    $topic->refresh();
    expect($topic->exercises_total)->toBe(30)
        ->and($topic->exercises_correct)->toBe(24)
        ->and($topic->accuracyPercent())->toBe(80);
});

test('recording more hits than questions is rejected', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $topic = Topic::factory()->for(Subject::factory()->for($plan))->create();

    $this->actingAs($user)
        ->put("/plano/topics/{$topic->id}/questoes", [
            'exercises_total' => 10,
            'exercises_correct' => 11,
        ])
        ->assertInvalid(['exercises_correct']);

    expect($topic->refresh()->exercises_total)->toBe(0);
});

test('a session adds to the hand-written total, and a later correction replaces it', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();
    $topic = Topic::factory()->for($subject)->create();
    $session = StudySession::factory()->for($user)->for($plan)->create([
        'state' => FocusSessionState::Running,
        'resumed_at' => now(),
    ]);

    // O par registrado na mão é o número oficial da tarefa.
    $this->actingAs($user)
        ->from(route('track.index'))
        ->put("/plano/topics/{$topic->id}/questoes", [
            'exercises_total' => 20,
            'exercises_correct' => 15,
        ]);

    // A sessão encerrada com questões soma — são questões novas, de outro
    // momento de estudo, não a mesma leva recontada.
    (new CompleteFocusSession)->handle(
        $session,
        $subject,
        $topic,
        ActivityType::Exercises,
        exercisesTotal: 10,
        exercisesCorrect: 9,
    );

    expect($topic->refresh()->exercises_total)->toBe(30)
        ->and($topic->exercises_correct)->toBe(24);

    // E o registro manual continua sendo a última palavra: quem viu o total
    // acumulado no diálogo e o corrigiu substitui o par inteiro.
    $this->actingAs($user)
        ->from(route('track.index'))
        ->put("/plano/topics/{$topic->id}/questoes", [
            'exercises_total' => 25,
            'exercises_correct' => 20,
        ]);

    expect($topic->refresh()->exercises_total)->toBe(25)
        ->and($topic->exercises_correct)->toBe(20);
});

test('recording questions requires ownership', function () {
    $user = User::factory()->create();
    $foreignTopic = Topic::factory()->create();

    $this->actingAs($user)
        ->put("/plano/topics/{$foreignTopic->id}/questoes", [
            'exercises_total' => 10,
            'exercises_correct' => 5,
        ])
        ->assertForbidden();
});

test('a task can be filed into an explicit cycle', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();

    Topic::factory()->for($subject)->inQueue(3, 5)->create();

    $this->actingAs($user)
        ->from(route('track.index'))
        ->post('/plano/topics', [
            'subject_id' => $subject->id,
            'name' => 'Tarefa do ciclo 1',
            'cycle' => 0,
        ]);

    $topic = Topic::where('name', 'Tarefa do ciclo 1')->sole();
    expect($topic->cycle)->toBe(0)
        ->and($topic->position)->toBe(6);
});
