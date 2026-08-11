<?php

use App\Models\CycleSlot;
use App\Models\Enums\SubjectArea;
use App\Models\StudyPlan;
use App\Models\Subject;
use App\Models\User;

test('a user builds a rotation and the focus screen announces whose turn it is', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create(['name' => 'Concurso XYZ']);
    Subject::factory()->for($plan)->create(['name' => 'Direito Constitucional']);
    Subject::factory()->for($plan)->create(['name' => 'Português']);

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/foco')
        ->assertNoJavaScriptErrors();

    $page->hover('[data-slot="sidebar"]')
        ->click('Ciclo')
        ->assertPathIs('/ciclo')
        ->assertSee('Nenhuma disciplina no rodízio ainda')
        ->assertNoJavaScriptErrors();

    $page->click('#cycle_subject')
        ->click('[role="option"]:has-text("Direito Constitucional")')
        ->click('button:text-is("Adicionar")')
        ->assertSee('Direito Constitucional')
        ->assertSee('É a vez')
        ->assertNoJavaScriptErrors();

    $page->click('#cycle_subject')
        ->click('[role="option"]:has-text("Português")')
        ->click('button:text-is("Adicionar")')
        ->assertSee('Volta 1')
        ->assertSee('0 de 2 blocos')
        ->assertNoJavaScriptErrors();

    // A vez do ciclo chega à tela de foco, e é ela que decide a disciplina.
    $page->hover('[data-slot="sidebar"]')
        ->click('Foco')
        ->assertPathIs('/foco')
        ->assertSee('A vez do ciclo')
        ->assertSee('volta 1')
        ->assertNoJavaScriptErrors();
});

test('a block picks up where it stopped after the page is left and reopened', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create(['name' => 'Concurso XYZ']);
    $subject = Subject::factory()->for($plan)->create(['name' => 'Direito Constitucional']);
    CycleSlot::factory()->create([
        'study_plan_id' => $plan->id,
        'subject_id' => $subject->id,
        'position' => 0,
        'block_minutes' => 60,
    ]);

    $this->actingAs($user);

    $page = visit('/foco');

    $page->click('button:has-text("Iniciar sessão de foco")')
        ->assertSee('Direito Constitucional')
        ->assertNoJavaScriptErrors();

    // Pausar congela o tempo no bloco. O relógio da sessão vive no
    // servidor, então sair da página não pode custar nada ao bloco.
    $page->click('[data-slot="focus-timer"] button:text-is("Pausar")')
        ->assertNoJavaScriptErrors();

    // Sai e volta pela navegação do app, não por `navigate()`: no plugin
    // de browser só a primeira navegação de um teste vale, e a partir da
    // segunda as asserções continuam olhando a página anterior. Pela
    // barra lateral o percurso é o mesmo que o do usuário.
    $page->hover('[data-slot="sidebar"]')
        ->click('Ciclo')
        ->assertPathIs('/ciclo')
        ->assertSee('É a vez')
        ->assertSee('de 1h 0min')
        ->assertNoJavaScriptErrors();

    // E a sessão continua de pé ao voltar: o bloco não foi encerrado por
    // ninguém ter saído da tela.
    $page->hover('[data-slot="sidebar"]')
        ->click('Foco')
        ->assertPathIs('/foco')
        ->assertSee('A vez do ciclo')
        ->assertNoJavaScriptErrors();
});

test('finishing the theory of a subject frees room in the rotation', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create(['name' => 'Concurso XYZ']);

    foreach (['Direito Constitucional', 'Português'] as $position => $name) {
        CycleSlot::factory()->create([
            'study_plan_id' => $plan->id,
            'subject_id' => Subject::factory()->for($plan)->create(['name' => $name])->id,
            'position' => $position,
            'block_minutes' => 90,
        ]);
    }

    $this->actingAs($user);

    $page = visit('/ciclo');

    $page->assertSee('90min')
        ->assertNoJavaScriptErrors();

    // Passar para revisão encolhe o bloco sozinho: é a folga assim
    // liberada que abre espaço para uma disciplina nova entrar.
    $page->click('[aria-label="Situação de Direito Constitucional"]')
        ->click('[role="option"]:has-text("Revisão")')
        ->assertSee('60min')
        ->assertNoJavaScriptErrors();

    // Encerrar tira do rodízio sem apagar: a disciplina continua na tela,
    // pronta para voltar.
    $page->click('[aria-label="Situação de Português"]')
        ->click('[role="option"]:has-text("Encerrada")')
        ->assertSee('Fora do rodízio')
        ->assertSee('Português')
        ->assertNoJavaScriptErrors();
});

test('a subject can be given a second passage in the same lap', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create(['name' => 'Concurso XYZ']);

    foreach (['Direito Constitucional', 'Português'] as $position => $name) {
        CycleSlot::factory()->create([
            'study_plan_id' => $plan->id,
            'subject_id' => Subject::factory()->for($plan)->create(['name' => $name])->id,
            'position' => $position,
            'block_minutes' => 60,
        ]);
    }

    $this->actingAs($user);

    $page = visit('/ciclo');

    $page->assertSee('0 de 2 blocos')
        ->assertNoJavaScriptErrors();

    // O peso se ajusta pelo mesmo botão que a duração: os dois números
    // dizem quanto a matéria custa na volta.
    $page->click('[aria-label="Ajustar o bloco de Direito Constitucional"]')
        ->fill('[aria-label="Passagens por volta de Direito Constitucional"]', '2')
        ->click('button:text-is("Salvar")')
        ->assertNoJavaScriptErrors();

    // A volta ganhou um bloco, e a linha passa a dizer de qual passagem
    // está falando.
    $page->assertSee('0 de 3 blocos')
        ->assertSee('60min ×2')
        ->assertSee('passagem 1 de 2')
        ->assertNoJavaScriptErrors();
});

test('the rotation warns about neighbouring families and reorders on request', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create(['name' => 'Concurso XYZ']);

    $subjects = [
        'Constitucional' => SubjectArea::Law,
        'Administrativo' => SubjectArea::Law,
        'Português' => SubjectArea::Languages,
    ];

    $position = 0;

    foreach ($subjects as $name => $area) {
        CycleSlot::factory()->create([
            'study_plan_id' => $plan->id,
            'subject_id' => Subject::factory()->for($plan)->create([
                'name' => $name,
                'area' => $area,
            ])->id,
            'position' => $position++,
        ]);
    }

    $this->actingAs($user);

    $page = visit('/ciclo');

    $page->assertSee('Constitucional e Administrativo')
        ->assertNoJavaScriptErrors();

    // O aviso some porque a ordem deixou de ter o problema, não porque
    // alguém o dispensou.
    $page->click('button:has-text("Intercalar áreas")')
        ->assertSee('Ordem intercalada')
        ->assertDontSee('Constitucional e Administrativo')
        ->assertNoJavaScriptErrors();
});

test('a block can be called done ahead of the target and the rotation moves on', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create(['name' => 'Concurso XYZ']);

    foreach (['Direito Constitucional', 'Português'] as $position => $name) {
        CycleSlot::factory()->create([
            'study_plan_id' => $plan->id,
            'subject_id' => Subject::factory()->for($plan)->create(['name' => $name])->id,
            'position' => $position,
        ]);
    }

    $this->actingAs($user);

    $page = visit('/ciclo');

    // Fechar um bloco convida para a próxima matéria — é o único momento
    // em que a intercalação fala com quem estuda.
    $page->click('button:text-is("Encerrar")')
        ->assertSee('Agora é Português')
        ->assertSee('1 de 2 blocos')
        ->assertNoJavaScriptErrors();

    // A vez passou para a próxima disciplina do rodízio.
    $page->assertSee('Concluído')
        ->assertSee('É a vez')
        ->assertNoJavaScriptErrors();
});
