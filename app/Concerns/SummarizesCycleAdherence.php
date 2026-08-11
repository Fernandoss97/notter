<?php

namespace App\Concerns;

use App\Models\CycleBlock;
use App\Models\CycleSlot;
use App\Models\Enums\FocusSessionState;
use App\Models\StudyPlan;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

trait SummarizesCycleAdherence
{
    /**
     * Janela da aderência. Quatro semanas pelo mesmo motivo do ritmo da
     * trilha: curta o bastante para descrever o mês corrente, longa o
     * bastante para uma semana ruim não afundar a média.
     */
    private const ADHERENCE_WINDOW_WEEKS = 4;

    /**
     * O que o ciclo prometeu contra o que de fato aconteceu.
     *
     * A comparação é entre *fatias*, não entre minutos absolutos: quem
     * estudou metade do planejado no mês cumpriu o rodízio se distribuiu
     * essa metade na proporção certa. Minutos absolutos só diriam que a
     * pessoa estudou pouco — coisa que a meta semanal já diz, e em outro
     * lugar.
     *
     * É a única tela que revela a disciplina sendo evitada: o plano mostra
     * tarefas concluídas, e quem foge de uma matéria simplesmente não
     * conclui nada nela, o que se confunde com "ainda não cheguei lá".
     *
     * @return array{closedLaps: int, blocksDone: int, rows: array<int, array{id: int, name: string, targetPercent: int, actualPercent: int, minutes: int, drift: int}>}|null
     */
    protected function cycleAdherence(StudyPlan $plan): ?array
    {
        $slots = $plan->cycleSlots()->active()->with('subject')->get();

        if ($slots->isEmpty()) {
            return null;
        }

        $minutesBySubject = $this->minutesBySubject($plan);
        $totalMinutes = (int) $minutesBySubject->sum();
        // O que a volta promete a cada matéria são os minutos do bloco
        // vezes as passagens dela — quem tem peso 2 pediu o dobro do tempo,
        // e a fatia-alvo precisa dizer isso.
        $totalTarget = (int) $slots->sum(fn (CycleSlot $slot) => $slot->block_minutes * $slot->blocks_per_lap);

        $rows = $slots->map(function (CycleSlot $slot) use ($minutesBySubject, $totalMinutes, $totalTarget) {
            $minutes = (int) $minutesBySubject->get($slot->subject_id, 0);
            $promised = $slot->block_minutes * $slot->blocks_per_lap;
            $target = $totalTarget > 0 ? (int) round($promised / $totalTarget * 100) : 0;
            $actual = $totalMinutes > 0 ? (int) round($minutes / $totalMinutes * 100) : 0;

            return [
                'id' => $slot->subject_id,
                'name' => $slot->subject->name,
                'targetPercent' => $target,
                'actualPercent' => $actual,
                'minutes' => $minutes,
                // Negativo é a matéria que está levando menos tempo do que
                // pediu — é esse sinal, e não o total, que denuncia a fuga.
                'drift' => $actual - $target,
            ];
        })->values()->all();

        [$closedLaps, $blocksDone] = $this->lapTally($slots);

        return [
            'closedLaps' => $closedLaps,
            'blocksDone' => $blocksDone,
            'rows' => $rows,
        ];
    }

    /**
     * Minutos de foco por disciplina na janela. Só sessões encerradas, e
     * só tempo de foco — o mesmo que o bloco cobra, para os dois números
     * na tela falarem da mesma coisa.
     *
     * @return Collection<int, int>
     */
    private function minutesBySubject(StudyPlan $plan): Collection
    {
        return $plan->studySessions()
            ->where('state', FocusSessionState::Completed)
            ->where('ended_at', '>=', now()->subWeeks(self::ADHERENCE_WINDOW_WEEKS))
            ->whereNotNull('subject_id')
            ->toBase()
            ->selectRaw('subject_id, COALESCE(SUM(accumulated_seconds), 0) as focus_seconds')
            ->groupBy('subject_id')
            ->pluck('focus_seconds', 'subject_id')
            ->map(fn (int|string $seconds) => (int) round((int) $seconds / 60));
    }

    /**
     * Voltas fechadas e blocos concluídos.
     *
     * Uma volta só conta como fechada quando nenhuma cadeira ativa deixou
     * bloco em aberto nela: é essa a unidade que diz "passei por todas as
     * matérias", e é ela que o método usa para saber se o ciclo está longo
     * demais.
     *
     * @param  EloquentCollection<int, CycleSlot>  $slots
     * @return array{0: int, 1: int}
     */
    private function lapTally(EloquentCollection $slots): array
    {
        $blocks = CycleBlock::query()
            ->whereIn('cycle_slot_id', $slots->modelKeys())
            ->get(['lap', 'completed_at']);

        $closed = $blocks
            ->groupBy('lap')
            ->filter(fn (Collection $lap) => $lap->every(fn (CycleBlock $block) => $block->isCompleted()))
            ->count();

        return [$closed, $blocks->filter(fn (CycleBlock $block) => $block->isCompleted())->count()];
    }
}
