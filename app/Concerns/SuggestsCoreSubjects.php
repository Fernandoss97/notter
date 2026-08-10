<?php

namespace App\Concerns;

use App\Models\StudyPlan;

trait SuggestsCoreSubjects
{
    /**
     * Quantas disciplinas o núcleo básico deve ter, a partir da carga
     * horária semanal do plano (§2 da spec).
     *
     * A spec cobre "menos de 20h" e "mais de 25h" e deixa a faixa do meio
     * em aberto — justamente onde cai o padrão do app, 20h cravadas. A
     * decisão tomada foi tratar a faixa como cinco disciplinas, subindo
     * para seis só a partir de 30h, onde o método já fala em "5 ou 6".
     *
     * É sugestão, nunca limite: nada no app impede um ciclo de dez
     * matérias, e o aviso de ciclo inchado já existe para quem exagerar.
     */
    protected function recommendedCoreSubjects(StudyPlan $plan): int
    {
        $weeklyHours = $plan->weekly_goal_minutes / 60;

        return match (true) {
            $weeklyHours < 20 => 4,
            $weeklyHours >= 30 => 6,
            default => 5,
        };
    }
}
