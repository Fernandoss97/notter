<?php

namespace App\Http\Controllers;

use App\Models\StudyPlan;
use App\Models\Topic;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudyTrackController extends Controller
{
    /**
     * A trilha: as tarefas do plano numa fila só, agrupadas em ciclos.
     *
     * A tela do plano responde "como está cada disciplina"; esta responde
     * "o que vem agora", que é uma pergunta diferente e cuja resposta
     * atravessa disciplinas — estudar é percorrer a fila, não esvaziar uma
     * disciplina de cada vez.
     */
    public function index(Request $request): Response
    {
        $plan = $request->user()->activeStudyPlan()->with('subjects')->first();

        return Inertia::render('track/index', [
            'plan' => $plan instanceof StudyPlan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'subjects' => $plan->subjects->map(fn ($subject) => [
                    'id' => $subject->id,
                    'name' => $subject->name,
                ])->all(),
            ] : null,
            'cycles' => $plan instanceof StudyPlan ? $this->cycles($plan) : [],
        ]);
    }

    /**
     * A fila quebrada em ciclos, cada tarefa carregando seu número na
     * sequência do plano inteiro (o "nº da tarefa" de uma planilha de
     * trilha). O número é derivado da posição na fila, não armazenado:
     * assim ele nunca fica furado depois de uma exclusão.
     *
     * @return array<int, array{cycle: int, done: int, total: int, tasks: array<int, array{id: int, number: int, name: string, subjectName: string, completed: bool, completedOn: string|null, reviewStatus: string|null}>}>
     */
    private function cycles(StudyPlan $plan): array
    {
        $number = 0;

        return $plan->topics()
            ->with(['subject', 'reviews'])
            ->get()
            ->map(function (Topic $topic) use (&$number) {
                $number++;

                return [
                    'cycle' => $topic->cycle,
                    'task' => [
                        'id' => $topic->id,
                        'number' => $number,
                        'name' => $topic->name,
                        'subjectName' => $topic->subject->name,
                        'completed' => $topic->isCompleted(),
                        'completedOn' => $topic->completed_at?->format('d/m/Y'),
                        'reviewStatus' => $topic->reviewStatus(),
                    ],
                ];
            })
            ->groupBy('cycle')
            ->map(function ($rows, int $cycle) {
                $tasks = $rows->pluck('task')->all();

                return [
                    'cycle' => $cycle,
                    'done' => collect($tasks)->where('completed', true)->count(),
                    'total' => count($tasks),
                    'tasks' => $tasks,
                ];
            })
            ->values()
            ->all();
    }
}
