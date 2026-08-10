<?php

namespace App\Concerns;

use App\Actions\Study\ResolveCurrentCycleBlock;
use App\Models\CycleBlock;
use App\Models\StudyPlan;

trait AnnouncesCycleTurn
{
    /**
     * O aviso de que um bloco fechou e de quem é a vez agora.
     *
     * É o único momento em que a intercalação fala com quem estuda: sem
     * este convite, o rodízio andaria calado e a próxima matéria só
     * apareceria para quem fosse conferir a tela do ciclo. Vale igual para
     * os dois caminhos que fecham um bloco — o tempo da sessão bater o
     * alvo, e o "dar por encerrado" manual.
     *
     * `$prefix` é a mensagem que a operação já tinha para dar ("Sessão
     * registrada."): fechar o bloco não a substitui, porque são dois fatos
     * e quem estuda precisa dos dois.
     */
    protected function cycleTurnMessage(
        StudyPlan $plan,
        CycleBlock $closed,
        ?string $prefix = null,
    ): string {
        $next = app(ResolveCurrentCycleBlock::class)->handle($plan);

        $message = __('Bloco de :subject concluído.', [
            'subject' => $closed->cycleSlot->subject->name,
        ]);

        if ($next instanceof CycleBlock) {
            $message .= ' '.__('Agora é :subject.', [
                'subject' => $next->cycleSlot->subject->name,
            ]);
        }

        return trim(($prefix ? $prefix.' ' : '').$message);
    }
}
