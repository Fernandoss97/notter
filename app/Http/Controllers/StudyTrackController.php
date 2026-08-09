<?php

namespace App\Http\Controllers;

use App\Actions\Study\Exceptions\InvalidTrackImportException;
use App\Actions\Study\ImportStudyTrack;
use App\Actions\Study\ParseTrackImport;
use App\Http\Requests\Study\ImportStudyTrackRequest;
use App\Models\StudyPlan;
use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
     * Importa uma trilha colada da planilha. O parser sabe apontar a linha
     * do erro, então a falha volta como erro de validação do próprio campo
     * — é lá que o usuário está olhando.
     */
    public function import(
        ImportStudyTrackRequest $request,
        ParseTrackImport $parse,
        ImportStudyTrack $import,
    ): RedirectResponse {
        $plan = $request->user()->activeStudyPlan()->first();

        if (! $plan instanceof StudyPlan) {
            throw ValidationException::withMessages([
                'content' => __('Ative um plano de estudos antes de importar uma trilha.'),
            ]);
        }

        try {
            $rows = $parse->handle($request->string('content')->toString());
        } catch (InvalidTrackImportException $exception) {
            throw ValidationException::withMessages(['content' => $exception->getMessage()]);
        }

        $summary = $import->handle($plan, $rows);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $this->importMessage($summary),
        ]);

        return to_route('track.index');
    }

    /**
     * @param  array{imported: int, skipped: int, subjectsCreated: int}  $summary
     */
    private function importMessage(array $summary): string
    {
        $parts = [trans_choice('{0}Nenhuma tarefa nova|{1}1 tarefa importada|[2,*]:count tarefas importadas', $summary['imported'], ['count' => $summary['imported']])];

        if ($summary['subjectsCreated'] > 0) {
            $parts[] = trans_choice('{1}1 disciplina criada|[2,*]:count disciplinas criadas', $summary['subjectsCreated'], ['count' => $summary['subjectsCreated']]);
        }

        if ($summary['skipped'] > 0) {
            $parts[] = trans_choice('{1}1 já estava na trilha|[2,*]:count já estavam na trilha', $summary['skipped'], ['count' => $summary['skipped']]);
        }

        return implode(' · ', $parts).'.';
    }

    /**
     * A fila quebrada em ciclos, cada tarefa carregando seu número na
     * sequência do plano inteiro (o "nº da tarefa" de uma planilha de
     * trilha). O número é derivado da posição na fila, não armazenado:
     * assim ele nunca fica furado depois de uma exclusão.
     *
     * @return array<int, array{cycle: int, done: int, total: int, exercises: int, correct: int, accuracy: int|null, tasks: array<int, array{id: int, number: int, name: string, subjectName: string, completed: bool, completedOn: string|null, reviewStatus: string|null, exercisesTotal: int, exercisesCorrect: int, accuracy: int|null}>}>
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
                        'exercisesTotal' => $topic->exercises_total,
                        'exercisesCorrect' => $topic->exercises_correct,
                        'accuracy' => $topic->accuracyPercent(),
                    ],
                ];
            })
            ->groupBy('cycle')
            ->map(function ($rows, int $cycle) {
                $tasks = collect($rows->pluck('task')->all());
                $exercises = (int) $tasks->sum('exercisesTotal');
                $correct = (int) $tasks->sum('exercisesCorrect');

                return [
                    'cycle' => $cycle,
                    'done' => $tasks->where('completed', true)->count(),
                    'total' => $tasks->count(),
                    'exercises' => $exercises,
                    'correct' => $correct,
                    'accuracy' => $exercises > 0 ? (int) round($correct / $exercises * 100) : null,
                    'tasks' => $tasks->all(),
                ];
            })
            ->values()
            ->all();
    }
}
