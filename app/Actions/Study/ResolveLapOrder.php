<?php

namespace App\Actions\Study;

use App\Models\CycleSlot;
use Illuminate\Support\Collection;

class ResolveLapOrder
{
    /**
     * A fila de uma volta: cada passagem de cada cadeira, na ordem em que
     * elas acontecem.
     *
     * Uma disciplina com peso 2 não estuda duas vezes seguidas — ela volta
     * depois das outras. Espalhar é a razão de o peso existir: quem quisesse
     * a sentada dobrada só precisava dobrar os minutos do bloco.
     *
     * A distribuição é o rodízio ingênuo, por rodadas: a rodada 1 chama
     * todas as cadeiras na ordem das posições, a rodada 2 chama as que têm
     * pelo menos duas passagens, e assim por diante. Com A(2), B(1) e C(1)
     * a volta sai A, B, C, A; com A(3) e B(1), A, B, A, A.
     *
     * Só aritmética inteira, e nenhum desempate escondido: a ordem precisa
     * ser a mesma toda vez que alguém perguntar, porque é ela que decide de
     * quem é a vez — e ela é derivada, nunca gravada, pelo mesmo motivo que
     * o ponteiro do ciclo (ver ResolveCurrentCycleBlock).
     *
     * @param  Collection<int, CycleSlot>  $slots  cadeiras ativas, em qualquer ordem
     * @return Collection<int, array{slot: CycleSlot, sequence: int}>
     */
    public function handle(Collection $slots): Collection
    {
        $ordered = $slots->sortBy('position')->values();
        $rounds = (int) $ordered->max('blocks_per_lap');

        return collect(range(1, max(1, $rounds)))
            ->flatMap(fn (int $round) => $ordered
                ->filter(fn (CycleSlot $slot) => $slot->blocks_per_lap >= $round)
                ->map(fn (CycleSlot $slot) => ['slot' => $slot, 'sequence' => $round])
                ->values()
            )
            ->values();
    }
}
