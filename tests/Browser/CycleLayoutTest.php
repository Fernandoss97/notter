<?php

use App\Models\CycleSlot;
use App\Models\Enums\SubjectArea;
use App\Models\Enums\SubjectMode;
use App\Models\StudyPlan;
use App\Models\Subject;
use App\Models\User;

/**
 * Um rodízio cheio, com todos os adornos ligados ao mesmo tempo: selo de
 * imersão, cadeira fora do rodízio, bloco em andamento e bloco concluído.
 *
 * A linha da cadeira carrega sete controles, e `assertSee` passa em texto
 * espremido numa coluna inutilizável — foi assim que a coluna do nome
 * chegou a zero sem nenhum teste reclamar. Este arquivo existe para deixar
 * uma captura dessa tela, que é o único jeito de ver o que os seletores
 * não veem.
 *
 * Entra pela porta da frente com `actingAs` em vez de preencher o login e
 * navegar até aqui: no plugin de browser só a primeira navegação de um
 * teste vale — um `navigate()` posterior chega deslogado e as asserções
 * seguem olhando a página anterior. Uma visita só, direto na tela que
 * interessa.
 */
test('a full rotation screen holds together', function () {
    $user = User::factory()->create();
    $plan = StudyPlan::factory()->for($user)->active()->create([
        'name' => 'Concurso XYZ',
        'weekly_goal_minutes' => 1800,
    ]);

    $subjects = [
        ['Direito Constitucional', SubjectArea::Law, SubjectMode::Reading, 90, true, 'active'],
        ['Direito Administrativo', SubjectArea::Law, SubjectMode::Reading, 60, false, 'active'],
        ['Português', SubjectArea::Languages, SubjectMode::Review, 60, false, 'active'],
        ['Raciocínio Lógico', SubjectArea::Exact, SubjectMode::Reading, 120, false, 'active'],
        ['Informática', SubjectArea::Other, SubjectMode::Done, 60, false, 'paused'],
    ];

    foreach ($subjects as $position => [$name, $area, $mode, $minutes, $boost, $state]) {
        CycleSlot::factory()->create([
            'study_plan_id' => $plan->id,
            'subject_id' => Subject::factory()->for($plan)->create([
                'name' => $name,
                'area' => $area,
                'mode' => $mode,
            ])->id,
            'position' => $position,
            'block_minutes' => $minutes,
            'boost_until' => $boost ? today()->addWeek() : null,
            'state' => $state,
        ]);
    }

    $this->actingAs($user);

    $page = visit('/ciclo');

    $page->assertSee('Direito Constitucional')
        ->assertSee('Direito Administrativo')
        ->assertSee('Raciocínio Lógico')
        ->assertSee('Informática')
        ->assertSee('Imersão')
        ->assertSee('Fora do rodízio')
        ->assertSee('Direito Constitucional e Direito Administrativo')
        ->assertNoJavaScriptErrors();

    $page->screenshot();
});
