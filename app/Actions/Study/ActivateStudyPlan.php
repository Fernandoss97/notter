<?php

namespace App\Actions\Study;

use App\Models\StudyPlan;
use Illuminate\Support\Facades\DB;

class ActivateStudyPlan
{
    /**
     * Torna `$plan` o plano ativo do usuário, desativando qualquer outro
     * plano ativo dele (A9: múltiplos planos por usuário, só um ativo).
     */
    public function handle(StudyPlan $plan): StudyPlan
    {
        DB::transaction(function () use ($plan): void {
            StudyPlan::whereBelongsTo($plan->user)
                ->where('is_active', true)
                ->where('id', '!=', $plan->id)
                ->update(['is_active' => false]);

            $plan->update(['is_active' => true]);
        });

        return $plan->refresh();
    }
}
