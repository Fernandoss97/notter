<?php

namespace App\Actions\Study;

use App\Models\CycleSlot;
use App\Models\Enums\CycleSlotState;
use App\Models\Enums\SubjectMode;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

class SetSubjectMode
{
    /**
     * Muda o pé em que a disciplina está e ajusta a cadeira dela no
     * rodízio — a inclusão gradual do §4.
     *
     * Terminar a teoria não tira a matéria do ciclo; reduz o que ela ocupa.
     * É a folga assim liberada que abre espaço para uma disciplina nova
     * entrar, e é por isso que o ajuste acontece aqui e não como uma
     * segunda edição que o usuário teria de lembrar de fazer.
     *
     * Encerrar sai do rodízio sem apagar nada: a matéria continua no
     * plano, com sua trilha e suas revisões, e pode voltar.
     *
     * O caminho de volta (revisão -> leitura) não devolve os minutos
     * sozinho: quem reabre a teoria de uma matéria sabe melhor que o
     * sistema quanto tempo ela vai pedir agora.
     */
    public function handle(Subject $subject, SubjectMode $mode): Subject
    {
        if ($subject->mode === $mode) {
            return $subject;
        }

        DB::transaction(function () use ($subject, $mode): void {
            $subject->update(['mode' => $mode]);

            $slot = $subject->cycleSlot()->first();

            if (! $slot instanceof CycleSlot) {
                return;
            }

            match ($mode) {
                SubjectMode::Review => $slot->update([
                    'block_minutes' => CycleSlot::DEFAULT_BLOCK_MINUTES,
                    'boost_until' => null,
                    'state' => CycleSlotState::Active,
                ]),
                SubjectMode::Done => $slot->update([
                    'boost_until' => null,
                    'state' => CycleSlotState::Paused,
                ]),
                SubjectMode::Reading => $slot->update([
                    'state' => CycleSlotState::Active,
                ]),
            };
        });

        return $subject->refresh();
    }
}
