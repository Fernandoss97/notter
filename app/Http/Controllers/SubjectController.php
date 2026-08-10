<?php

namespace App\Http\Controllers;

use App\Actions\Study\SetSubjectMode;
use App\Http\Requests\Study\StoreSubjectRequest;
use App\Http\Requests\Study\UpdateSubjectRequest;
use App\Models\Enums\SubjectArea;
use App\Models\Enums\SubjectMode;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SubjectController extends Controller
{
    /**
     * Add a subject to the user's active plan. There is no subject_id
     * param to scope by here: a subject always belongs to whichever plan
     * is currently active for the user, never one picked from the client.
     */
    public function store(StoreSubjectRequest $request): RedirectResponse
    {
        $plan = $request->user()->activeStudyPlan;

        if (! $plan) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Crie um plano de estudos antes de adicionar disciplinas.')]);

            return to_route('plans.index');
        }

        $plan->subjects()->create([
            ...$request->validated(),
            'position' => $plan->subjects()->count(),
        ]);

        return to_route('plans.index');
    }

    /**
     * Edita o que a disciplina é para o ciclo: o pé em que ela está
     * (leitura, revisão, encerrada) e a família a que pertence.
     *
     * O modo passa por uma Action porque mexe na cadeira do rodízio junto:
     * a matéria que sai da teoria ocupa menos tempo, e é a folga liberada
     * que abre espaço para uma disciplina nova. A família é só uma
     * etiqueta — ela não muda nada sozinha, apenas alimenta a sugestão de
     * ordem intercalada.
     */
    public function update(
        UpdateSubjectRequest $request,
        Subject $subject,
        SetSubjectMode $action,
    ): RedirectResponse {
        abort_unless($subject->studyPlan->user_id === $request->user()->id, 403);

        if ($request->filled('area')) {
            $subject->update(['area' => SubjectArea::from($request->string('area')->toString())]);
        }

        if ($request->filled('mode')) {
            $action->handle($subject, SubjectMode::from($request->string('mode')->toString()));
        }

        return back();
    }
}
