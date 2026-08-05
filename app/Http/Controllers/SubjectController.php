<?php

namespace App\Http\Controllers;

use App\Http\Requests\Study\StoreSubjectRequest;
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
}
