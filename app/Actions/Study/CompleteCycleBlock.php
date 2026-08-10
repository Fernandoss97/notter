<?php

namespace App\Actions\Study;

use App\Actions\Study\Exceptions\InvalidCycleTransitionException;
use App\Models\CycleBlock;

class CompleteCycleBlock
{
    /**
     * Fecha o bloco e, com isso, faz o rodízio andar — o ponteiro é
     * derivado, então fechar aqui é a única forma de avançar.
     *
     * Fechar antes do alvo é legítimo e não é erro: é o "feito é melhor
     * que perfeito" da spec (§6) virando botão. O tempo acumulado fica
     * como está, para o Progresso poder mostrar quanto do bloco foi
     * realmente cumprido.
     *
     * @throws InvalidCycleTransitionException
     */
    public function handle(CycleBlock $block): CycleBlock
    {
        if ($block->isCompleted()) {
            throw InvalidCycleTransitionException::blockAlreadyCompleted();
        }

        $block->update(['completed_at' => now()]);

        return $block->refresh();
    }
}
