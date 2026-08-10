<?php

namespace App\Actions\Study;

use App\Actions\Study\Exceptions\InvalidFocusSessionTransitionException;
use App\Models\CycleBlock;
use App\Models\Enums\FocusSessionState;
use App\Models\PomodoroSetting;
use App\Models\StudyPlan;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;

class StartFocusSession
{
    public function __construct(private ResolveCurrentCycleBlock $resolveCurrentBlock) {}

    /**
     * Inicia uma sessão de foco para o usuário (B1/B7). `planned_seconds`
     * é um snapshot da duração de foco configurada no momento do início —
     * mudanças posteriores em PomodoroSetting não afetam sessões em
     * andamento. Só uma sessão não concluída por usuário por vez.
     */
    public function handle(
        User $user,
        StudyPlan $studyPlan,
        ?Subject $subject = null,
        ?Topic $topic = null,
    ): StudySession {
        $hasActiveSession = $user->studySessions()
            ->where('state', '!=', FocusSessionState::Completed)
            ->exists();

        if ($hasActiveSession) {
            throw InvalidFocusSessionTransitionException::alreadyActive();
        }

        $pomodoroSetting = $user->pomodoroSetting()->first();
        $focusMinutes = $pomodoroSetting instanceof PomodoroSetting
            ? $pomodoroSetting->focus_minutes
            : (new PomodoroSetting)->focus_minutes;

        $block = $this->blockToCredit($studyPlan, $subject);
        $now = now();

        return StudySession::create([
            'user_id' => $user->id,
            'study_plan_id' => $studyPlan->id,
            'subject_id' => $subject instanceof Subject
                ? $subject->id
                : $block?->cycleSlot->subject_id,
            'topic_id' => $topic?->id,
            'cycle_block_id' => $block?->id,
            'state' => FocusSessionState::Running,
            'planned_seconds' => $focusMinutes * 60,
            'started_at' => $now,
            'resumed_at' => $now,
        ]);
    }

    /**
     * O bloco do rodízio que esta sessão alimenta, se algum.
     *
     * O vínculo é decidido aqui e não no encerramento: trocar de
     * disciplina no meio da sessão reescreveria o passado do bloco.
     *
     * Estudar outra disciplina que não a da vez é legítimo e simplesmente
     * não credita o rodízio — quem sai da fila não faz a fila andar. Sem
     * disciplina escolhida, a do bloco corrente é assumida, que é o que a
     * tela de foco já pré-seleciona.
     */
    private function blockToCredit(StudyPlan $studyPlan, ?Subject $subject): ?CycleBlock
    {
        $block = $this->resolveCurrentBlock->handle($studyPlan);

        if (! $block instanceof CycleBlock) {
            return null;
        }

        return $subject === null || $subject->id === $block->cycleSlot->subject_id
            ? $block
            : null;
    }
}
