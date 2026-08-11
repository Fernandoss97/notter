<?php

namespace App\Actions\Study;

use App\Models\CycleBlock;
use App\Models\CycleSlot;
use App\Models\StudyPlan;
use Illuminate\Database\Eloquent\Collection;

class ResolveCurrentCycleBlock
{
    /**
     * A fila da volta vem por injeção, mas com um padrão: esta ação é
     * instanciada direto (`new ResolveCurrentCycleBlock`) em dezenas de
     * testes que nada têm a ver com ordenação, e obrigá-los a montar a
     * dependência seria ruído em todos eles. `ResolveLapOrder` não tem
     * estado nem colaborador — construí-la aqui não esconde nada.
     */
    public function __construct(private ResolveLapOrder $lapOrder = new ResolveLapOrder) {}

    /**
     * O bloco corrente do rodízio — o único caminho até ele.
     *
     * O ponteiro do ciclo não é armazenado, é derivado, pelo mesmo motivo
     * que o número da tarefa na trilha: um ponteiro guardado fica furado
     * assim que uma cadeira é pausada, reordenada ou removida, e ninguém
     * descobre até o rodízio pular uma matéria.
     *
     * Materializar a volta aqui é o efeito colateral aceito de uma leitura
     * (mesmo padrão de `pomodoroSetting()->firstOrCreate([])`): a volta só
     * existe quando alguém pergunta o que estudar agora.
     */
    public function handle(StudyPlan $plan): ?CycleBlock
    {
        $slots = $plan->cycleSlots()->active()->get();

        if ($slots->isEmpty()) {
            return null;
        }

        $lap = $this->currentLap($slots);

        $this->openLap($slots, $lap);

        return $this->firstOpenBlock($slots, $lap);
    }

    /**
     * A volta em andamento: a mais antiga com bloco em aberto. Sem nenhum
     * bloco aberto, a próxima volta começa depois da última registrada —
     * e não em 1, que reabriria o ciclo inteiro toda vez.
     *
     * O número da volta pode recuar, e isso é intencional: uma cadeira
     * pausada no meio de um bloco leva o bloco aberto consigo, o resto da
     * volta fecha, e a volta seguinte começa. Ao devolvê-la ao rodízio, o
     * bloco dela volta a ser o mais antigo em aberto e a tela volta a dizer
     * "volta 1". Preferir a volta mais nova esconderia esse bloco para
     * sempre — e ele tem tempo estudado dentro.
     *
     * @param  Collection<int, CycleSlot>  $slots
     */
    private function currentLap(Collection $slots): int
    {
        $slotIds = $slots->modelKeys();

        $openLap = CycleBlock::query()
            ->whereIn('cycle_slot_id', $slotIds)
            ->open()
            ->min('lap');

        if ($openLap !== null) {
            return (int) $openLap;
        }

        return (int) CycleBlock::query()->whereIn('cycle_slot_id', $slotIds)->max('lap') + 1;
    }

    /**
     * Garante um bloco por cadeira ativa na volta corrente. Serve às duas
     * situações: abrir a volta seguinte quando a anterior fechou, e dar
     * lugar imediato a uma disciplina acrescentada no meio da volta — quem
     * entra no rodízio entra agora, não na próxima passagem.
     *
     * `target_seconds` é congelado aqui: mudar a duração da cadeira não
     * reescreve um bloco já aberto, como `planned_seconds` não reescreve
     * uma sessão em andamento.
     *
     * A escrita é `insertOrIgnore` e não um create por cadeira faltante:
     * esta ação roda em toda visita ao foco e ao ciclo, e duas abas abertas
     * ao mesmo tempo veriam ambas a volta vazia e tentariam abri-la. Com o
     * create, a segunda estouraria no índice único
     * `(cycle_slot_id, lap, sequence)` e derrubaria a tela principal do app
     * com um 500. O índice está certo; o que precisava mudar era a escrita.
     * Duas abas já são cenário previsto aqui — ver AdvanceFocusPhase.
     *
     * A `sequence` é sempre 1..N, calculada do peso e de mais nada. Derivá-la
     * de uma contagem de linhas ("as que existem, mais um") destruiria a
     * idempotência de que o parágrafo acima depende.
     *
     * @param  Collection<int, CycleSlot>  $slots
     */
    private function openLap(Collection $slots, int $lap): void
    {
        $this->dropSurplusBlocks($slots, $lap);

        $now = now();

        $rows = $this->lapOrder->handle($slots)->map(fn (array $passage) => [
            'cycle_slot_id' => $passage['slot']->id,
            'lap' => $lap,
            'sequence' => $passage['sequence'],
            'target_seconds' => $passage['slot']->targetSeconds(),
            'accumulated_seconds' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        CycleBlock::query()->insertOrIgnore($rows);
    }

    /**
     * Passagens que sobraram de um peso maior, na volta em andamento.
     *
     * Baixar o peso no meio da volta deixaria blocos abertos que ninguém
     * mais vai estudar — e volta com bloco aberto não fecha, então o rodízio
     * pararia ali para sempre. Some quem ainda não tinha nada dentro.
     *
     * Quem já acumulou tempo fica: é a mesma promessa que faz o número da
     * volta recuar quando uma cadeira volta do descanso — bloco com tempo
     * dentro não desaparece sem alguém ver. Esse fecha estudando ou pelo
     * botão de encerrar, e o peso novo vale da volta seguinte.
     *
     * @param  Collection<int, CycleSlot>  $slots
     */
    private function dropSurplusBlocks(Collection $slots, int $lap): void
    {
        $weights = $slots->pluck('blocks_per_lap', 'id');

        $surplus = CycleBlock::query()
            ->whereIn('cycle_slot_id', $slots->modelKeys())
            ->where('lap', $lap)
            ->open()
            ->where('accumulated_seconds', 0)
            ->get()
            ->filter(fn (CycleBlock $block) => $block->sequence > (int) $weights->get($block->cycle_slot_id, 1));

        if ($surplus->isNotEmpty()) {
            CycleBlock::query()->whereKey($surplus->modelKeys())->delete();
        }
    }

    /**
     * O bloco da vez dentro da volta. Um bloco já começado ganha de
     * qualquer outro: o rodízio não abandona bloco pela metade, que é a
     * continuidade que a spec pede (§3). Só entre blocos intocados a
     * fila da volta decide.
     *
     * @param  Collection<int, CycleSlot>  $slots
     */
    private function firstOpenBlock(Collection $slots, int $lap): ?CycleBlock
    {
        $blocks = CycleBlock::query()
            ->whereIn('cycle_slot_id', $slots->modelKeys())
            ->where('lap', $lap)
            ->open()
            ->with('cycleSlot.subject')
            ->get()
            ->sortBy($this->queuePosition($slots))
            ->values();

        return $blocks->first(fn (CycleBlock $block) => $block->accumulated_seconds > 0)
            ?? $blocks->first();
    }

    /**
     * Onde cada bloco cai na fila da volta. Blocos que a fila não conhece —
     * a sobra com tempo dentro, que o peso menor deixou para trás — vão para
     * o fim, para serem oferecidos depois do que ainda está no plano.
     *
     * @param  Collection<int, CycleSlot>  $slots
     * @return callable(CycleBlock): int
     */
    private function queuePosition(Collection $slots): callable
    {
        $queue = $this->lapOrder->handle($slots)
            ->mapWithKeys(fn (array $passage, int $index) => [
                $passage['slot']->id.':'.$passage['sequence'] => $index,
            ]);

        return fn (CycleBlock $block) => $queue->get(
            $block->cycle_slot_id.':'.$block->sequence,
            PHP_INT_MAX,
        );
    }
}
