<?php

namespace App\Concerns;

use App\Models\CycleBlock;
use App\Models\StudySession;

trait SummarizesCycleBlock
{
    /**
     * Um bloco do rodízio no formato que as telas consomem.
     *
     * `elapsedSeconds` soma o segmento ainda vivo da sessão em andamento —
     * o bloco só é reconciliado nas transições, então sem isso a barra
     * ficaria parada enquanto o cronômetro roda. Mesmo padrão de
     * SummarizesStudyTime, e só vale para a sessão que alimenta *este*
     * bloco: quem saiu da fila para estudar outra coisa não o move.
     *
     * @return array{id: int, subjectId: int, subjectName: string, lap: int, targetMinutes: int, elapsedSeconds: int, remainingMinutes: int, progressPercent: int, completed: bool, isActive: bool}|null
     */
    protected function cycleBlockProp(?CycleBlock $block, ?StudySession $session = null): ?array
    {
        if (! $block instanceof CycleBlock) {
            return null;
        }

        $isActive = $session instanceof StudySession && $session->cycle_block_id === $block->id;
        $elapsed = $block->accumulated_seconds + ($isActive ? $session->liveFocusSeconds() : 0);

        return [
            'id' => $block->id,
            'subjectId' => $block->cycleSlot->subject_id,
            'subjectName' => $block->cycleSlot->subject->name,
            'lap' => $block->lap,
            'targetMinutes' => (int) round($block->target_seconds / 60),
            'elapsedSeconds' => $elapsed,
            'remainingMinutes' => (int) ceil(max(0, $block->target_seconds - $elapsed) / 60),
            'progressPercent' => $block->target_seconds > 0
                ? (int) min(100, round($elapsed / $block->target_seconds * 100))
                : 0,
            'completed' => $block->isCompleted(),
            'isActive' => $isActive,
        ];
    }
}
