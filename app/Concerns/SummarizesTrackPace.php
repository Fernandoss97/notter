<?php

namespace App\Concerns;

use App\Models\StudyPlan;
use App\Models\Topic;

trait SummarizesTrackPace
{
    /**
     * Janela usada para medir o ritmo atual. Quatro semanas é curto o
     * bastante para refletir o mês corrente e longo o bastante para uma
     * semana ruim não afundar a média.
     */
    private const PACE_WINDOW_WEEKS = 4;

    /**
     * O prazo contra o ritmo: quantas tarefas faltam, quantas precisam
     * sair por semana até a prova e quantas estão saindo de fato.
     *
     * `requiredPerWeek` é `null` quando não há data de prova ou quando ela
     * já passou — não há ritmo que salve um prazo vencido. `projectedOn` é
     * `null` para quem não concluiu nada na janela: dividir por ritmo zero
     * daria "nunca", que é uma previsão inútil de mostrar.
     *
     * @return array{tasksRemaining: int, tasksTotal: int, examOn: string|null, daysUntilExam: int|null, requiredPerWeek: float|null, currentPerWeek: float, projectedOn: string|null, onTrack: bool|null}
     */
    protected function trackPace(StudyPlan $plan): array
    {
        $topics = $plan->topics()->get();
        $total = $topics->count();
        $remaining = $topics->filter(fn (Topic $topic) => ! $topic->isCompleted())->count();

        $doneInWindow = $topics
            ->filter(fn (Topic $topic) => $topic->completed_at !== null
                && $topic->completed_at->greaterThanOrEqualTo(now()->subWeeks(self::PACE_WINDOW_WEEKS)))
            ->count();

        $currentPerWeek = round($doneInWindow / self::PACE_WINDOW_WEEKS, 1);
        $days = $plan->daysUntilExam();
        $requiredPerWeek = $days !== null && $days > 0 && $remaining > 0
            ? round($remaining / ($days / 7), 1)
            : null;

        return [
            'tasksRemaining' => $remaining,
            'tasksTotal' => $total,
            'examOn' => $plan->exam_date?->format('d/m/Y'),
            'daysUntilExam' => $days,
            'requiredPerWeek' => $requiredPerWeek,
            'currentPerWeek' => $currentPerWeek,
            'projectedOn' => $currentPerWeek > 0 && $remaining > 0
                ? now()->addDays((int) ceil($remaining / $currentPerWeek * 7))->format('d/m/Y')
                : null,
            'onTrack' => $requiredPerWeek === null ? null : $currentPerWeek >= $requiredPerWeek,
        ];
    }
}
