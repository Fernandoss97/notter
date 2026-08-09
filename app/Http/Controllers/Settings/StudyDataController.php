<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StudyDataDeleteRequest;
use App\Models\StudyPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StudyDataController extends Controller
{
    /**
     * Apaga o que o usuário estudou, mantendo quem ele é: planos,
     * disciplinas, tarefas, revisões e sessões vão embora; conta, senha,
     * passkeys, 2FA e preferências do pomodoro ficam.
     *
     * É o recomeço de quem terminou um concurso e vai para o próximo sem
     * querer levar a trilha antiga junto — antes, a única forma de zerar
     * era excluir a conta inteira.
     */
    public function destroy(StudyDataDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user): void {
            // Disciplinas, tarefas e revisões caem em cascata a partir do
            // plano; as sessões também, porque `study_plan_id` é
            // obrigatório e cascateia (ver a migration de study_sessions).
            //
            // Os planos são carregados antes de apagar: iterar a query
            // enquanto se apaga dela é o jeito clássico de pular registros.
            $user->studyPlans()->get()->each(fn (StudyPlan $plan) => $plan->delete());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dados de estudo apagados.')]);

        return to_route('profile.edit');
    }
}
