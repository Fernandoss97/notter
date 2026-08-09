<?php

namespace App\Http\Controllers;

use App\Actions\Study\ActivateStudyPlan;
use App\Concerns\ListsStudyPlans;
use App\Http\Requests\Study\StoreStudyPlanRequest;
use App\Http\Requests\Study\UpdateStudyPlanRequest;
use App\Models\StudyPlan;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudyPlanController extends Controller
{
    use ListsStudyPlans;

    /**
     * Show every plan the user has (A9) and the full subject/topic
     * breakdown of whichever one is active.
     */
    public function index(Request $request): Response
    {
        $activePlan = $request->user()->activeStudyPlan()->with('subjects.topics.reviews')->first();

        return Inertia::render('plan/index', [
            'plans' => $this->planOptions($request),
            'activePlan' => $activePlan ? [
                'id' => $activePlan->id,
                'name' => $activePlan->name,
                'type' => $activePlan->type->value,
                'weeklyGoalMinutes' => $activePlan->weekly_goal_minutes,
                // No formato do input date, não no de leitura: este valor
                // volta para o formulário de edição.
                'examDate' => $activePlan->exam_date?->toDateString(),
                'subjects' => $activePlan->subjects->map($this->subjectProp(...))->all(),
            ] : null,
        ]);
    }

    /**
     * Create a plan and make it the active one straight away — a brand
     * new plan with nothing in it is never useful sitting inactive.
     */
    public function store(StoreStudyPlanRequest $request, ActivateStudyPlan $activate): RedirectResponse
    {
        $plan = $request->user()->studyPlans()->create($request->validated());

        $activate->handle($plan);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plano criado.')]);

        return to_route('plans.index');
    }

    /**
     * Volta para a tela de onde a troca partiu. O seletor de plano vive em
     * quatro telas: mandar todas para o plano tiraria o usuário de onde ele
     * estava justamente para mostrar os números do plano que ele acabou de
     * escolher.
     */
    public function activate(Request $request, StudyPlan $plan, ActivateStudyPlan $action): RedirectResponse
    {
        abort_unless($plan->user_id === $request->user()->id, 403);

        $action->handle($plan);

        return back();
    }

    public function update(UpdateStudyPlanRequest $request, StudyPlan $plan): RedirectResponse
    {
        abort_unless($plan->user_id === $request->user()->id, 403);

        $plan->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plano atualizado.')]);

        return to_route('plans.index');
    }

    /**
     * @return array{id: int, name: string, goalMinutes: int|null, topics: array<int, array{id: int, name: string, cycle: int, completed: bool, reviewStatus: string|null}>}
     */
    private function subjectProp(Subject $subject): array
    {
        return [
            'id' => $subject->id,
            'name' => $subject->name,
            'goalMinutes' => $subject->goal_minutes,
            'topics' => $subject->topics->map($this->topicProp(...))->all(),
        ];
    }

    /**
     * @return array{id: int, name: string, cycle: int, completed: bool, reviewStatus: string|null}
     */
    private function topicProp(Topic $topic): array
    {
        return [
            'id' => $topic->id,
            'name' => $topic->name,
            'cycle' => $topic->cycle,
            'completed' => $topic->isCompleted(),
            'reviewStatus' => $topic->reviewStatus(),
        ];
    }
}
