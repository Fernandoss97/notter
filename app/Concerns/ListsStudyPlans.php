<?php

namespace App\Concerns;

use App\Models\StudyPlan;
use Illuminate\Http\Request;

trait ListsStudyPlans
{
    /**
     * Os planos do usuário para o seletor de plano ativo.
     *
     * A ordem é alfabética e estável: o seletor é feito de alvos
     * clicáveis, e promover o ativo para a frente os faria dançar a cada
     * troca. Qual deles está ativo não viaja aqui — quem marca a seleção é
     * o plano que a própria tela já recebe.
     *
     * @return array<int, array{id: int, name: string}>
     */
    protected function planOptions(Request $request): array
    {
        return $request->user()->studyPlans()
            ->orderBy('name')
            ->get()
            ->map(fn (StudyPlan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
            ])
            ->all();
    }
}
