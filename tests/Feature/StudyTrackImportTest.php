<?php

use App\Models\StudyPlan;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;

function trackPaste(array $lines): string
{
    return implode("\n", $lines);
}

test('guests cannot import a track', function () {
    $this->post('/trilha/importar', ['content' => "Português\tAula 01"])
        ->assertRedirect('/login');
});

test('importing creates the missing subjects and queues the tasks', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();

    $this->actingAs($user)
        ->post('/trilha/importar', ['content' => trackPaste([
            "TRILHA\tTAREFA\tDISCIPLINA\tTÍTULO DA TAREFA",
            "0\t1\tBanco de Dados\tEstudo da Aula 00",
            "0\t2\tRaciocínio Lógico\tEstruturas Lógicas I",
            "1\t3\tBanco de Dados\tEstudo da Aula 01",
        ])])
        ->assertRedirect(route('track.index'));

    expect($plan->subjects()->pluck('name')->all())
        ->toBe(['Banco de Dados', 'Raciocínio Lógico']);

    $topics = $plan->topics()->get();
    expect($topics)->toHaveCount(3)
        ->and($topics->pluck('name')->all())->toBe([
            'Estudo da Aula 00',
            'Estruturas Lógicas I',
            'Estudo da Aula 01',
        ])
        ->and($topics->pluck('cycle')->all())->toBe([0, 0, 1])
        ->and($topics->pluck('position')->all())->toBe([0, 1, 2]);
});

test('an existing subject is reused, whatever the casing', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create(['name' => 'Banco de Dados']);

    $this->actingAs($user)
        ->post('/trilha/importar', ['content' => "  banco de dados \tEstudo da Aula 00"]);

    expect($plan->subjects()->count())->toBe(1)
        ->and($subject->topics()->count())->toBe(1);
});

test('re-importing the same paste adds only what is new', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();

    $first = trackPaste([
        "0\t1\tBanco de Dados\tEstudo da Aula 00",
        "0\t2\tBanco de Dados\tEstudo da Aula 01",
    ]);

    $this->actingAs($user)->post('/trilha/importar', ['content' => $first]);

    // A trilha ganha tarefas novas toda semana: recolar a planilha inteira
    // precisa acrescentar só a linha que chegou.
    $this->actingAs($user)->post('/trilha/importar', ['content' => trackPaste([
        "0\t1\tBanco de Dados\tEstudo da Aula 00",
        "0\t2\tBanco de Dados\tEstudo da Aula 01",
        "0\t3\tBanco de Dados\tEstudo da Aula 02",
    ])]);

    expect($plan->topics()->count())->toBe(3)
        ->and($plan->topics()->get()->last()->name)->toBe('Estudo da Aula 02');
});

test('the same title in a later cycle is another task', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();

    $this->actingAs($user)->post('/trilha/importar', ['content' => trackPaste([
        "0\t1\tBanco de Dados\tEstudo da Aula 00",
        "1\t2\tBanco de Dados\tEstudo da Aula 00",
    ])]);

    expect($plan->topics()->count())->toBe(2);
});

test('tasks without a cycle land in the current one', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    $subject = Subject::factory()->for($plan)->create();
    Topic::factory()->for($subject)->inQueue(3, 9)->create();

    $this->actingAs($user)->post('/trilha/importar', [
        'content' => "Português\tAula 01",
    ]);

    $imported = Topic::where('name', 'Aula 01')->sole();
    expect($imported->cycle)->toBe(3)
        ->and($imported->position)->toBe(10);
});

test('imported tasks go to the end of an existing queue', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create();
    Topic::factory()->for(Subject::factory()->for($plan))->inQueue(0, 0)->create();

    $this->actingAs($user)->post('/trilha/importar', [
        'content' => "Português\tAula 01",
    ]);

    expect(Topic::where('name', 'Aula 01')->sole()->position)->toBe(1);
});

test('a malformed line fails validation pointing at the line', function () {
    $user = User::factory()->create();
    StudyPlan::factory()->for($user)->active()->create();

    $this->actingAs($user)
        ->post('/trilha/importar', ['content' => trackPaste([
            "0\t1\tBanco de Dados\tEstudo da Aula 00",
            "0\tBanco de Dados\tsem uma coluna",
        ])])
        ->assertInvalid(['content']);

    // Nada entra: uma importação parcial deixaria o usuário sem saber o
    // que precisa recolar.
    expect(Topic::count())->toBe(0);
});

test('importing without an active plan fails validation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/trilha/importar', ['content' => "Português\tAula 01"])
        ->assertInvalid(['content']);

    expect(Topic::count())->toBe(0);
});

test('importing into another user plan is not possible', function () {
    $user = User::factory()->create();
    $mine = StudyPlan::factory()->for($user)->active()->create();
    $foreign = StudyPlan::factory()->active()->create();

    $this->actingAs($user)->post('/trilha/importar', [
        'content' => "Português\tAula 01",
    ]);

    // A importação sempre cai no plano ativo de quem está logado; não há
    // como endereçar outro plano.
    expect($mine->topics()->count())->toBe(1)
        ->and($foreign->topics()->count())->toBe(0);
});
