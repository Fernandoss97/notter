<?php

namespace App\Http\Controllers;

use App\Actions\Study\CompleteTopic;
use App\Http\Requests\Study\StoreTopicRequest;
use App\Models\StudyPlan;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TopicController extends Controller
{
    /**
     * subject_id is already ownership-validated by StoreTopicRequest's
     * after() (must belong to the user's active plan).
     *
     * A tarefa entra no fim da fila da trilha: `position` é a sequência
     * dentro do plano, não dentro da disciplina, e o ciclo default é
     * aquele em que a trilha já está.
     */
    public function store(StoreTopicRequest $request): RedirectResponse
    {
        $subject = Subject::findOrFail($request->integer('subject_id'));
        $plan = $subject->studyPlan;

        $subject->topics()->create([
            'name' => $request->string('name')->toString(),
            'cycle' => $request->filled('cycle')
                ? $request->integer('cycle')
                : $this->currentCycle($plan),
            'position' => $this->nextPosition($plan),
        ]);

        return back();
    }

    public function complete(Request $request, Topic $topic, CompleteTopic $action): RedirectResponse
    {
        abort_unless($topic->subject->studyPlan->user_id === $request->user()->id, 403);

        $action->handle($topic);

        return back();
    }

    /**
     * O ciclo corrente da trilha é o último aberto — plano recém-criado,
     * sem tarefa nenhuma, começa no ciclo 0.
     */
    private function currentCycle(StudyPlan $plan): int
    {
        return (int) ($plan->topics()->max('topics.cycle') ?? 0);
    }

    private function nextPosition(StudyPlan $plan): int
    {
        $last = $plan->topics()->max('topics.position');

        return $last === null ? 0 : (int) $last + 1;
    }
}
