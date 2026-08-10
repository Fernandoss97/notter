<?php

namespace App\Actions\Study;

use App\Models\CycleBlock;

class SyncCycleBlockProgress
{
    /**
     * Reconcilia o bloco com as sessões que o alimentam, e o encerra se o
     * alvo foi alcançado.
     *
     * Recalcula em vez de incrementar de propósito. O acumulador de foco
     * da sessão cresce em quatro lugares distintos (pausar, avançar de
     * fase, encerrar e fechar sessão abandonada); um incremento por
     * gancho significaria que esquecer um deles faz o bloco contar a
     * menos para sempre, em silêncio. A soma inteira é idempotente: o
     * gancho seguinte conserta o anterior.
     *
     * Só o tempo de *foco* entra (`accumulated_seconds`), mesmo para quem
     * prefere contar os intervalos como tempo estudado — essa preferência
     * governa o que o dashboard exibe, não o que o bloco cobra. Contando
     * intervalo, um bloco de 90 minutos fecharia com cerca de 70 de
     * estudo real.
     *
     * O segmento vivo da sessão em andamento não entra aqui: ele só
     * existe enquanto o cronômetro roda, e quem o soma para exibição é a
     * tela (ver CycleBlock::progressPercent e o mesmo padrão em
     * SummarizesStudyTime).
     */
    public function handle(CycleBlock $block): CycleBlock
    {
        $block->update([
            'accumulated_seconds' => (int) $block->studySessions()->sum('accumulated_seconds'),
        ]);

        $block->refresh();

        if (! $block->isCompleted() && $block->hasReachedTarget()) {
            $block->update(['completed_at' => now()]);
            $block->refresh();
        }

        return $block;
    }
}
