<?php

namespace App\Http\Controllers;

use App\Actions\Study\CompleteCycleBlock;
use App\Actions\Study\Exceptions\InvalidCycleTransitionException;
use App\Concerns\AnnouncesCycleTurn;
use App\Models\CycleBlock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CycleBlockController extends Controller
{
    use AnnouncesCycleTurn;

    /**
     * Dá o bloco por encerrado antes do alvo.
     *
     * Não é atalho nem trapaça: é o "feito é melhor que perfeito" da spec
     * (§6). Quem terminou o que tinha para fazer naquela matéria não deve
     * ficar preso a ela porque faltam quinze minutos no relógio — o tempo
     * já cumprido fica registrado, e o rodízio anda.
     */
    public function complete(Request $request, CycleBlock $cycleBlock, CompleteCycleBlock $action): RedirectResponse
    {
        abort_unless(
            $cycleBlock->cycleSlot->studyPlan->user_id === $request->user()->id,
            403
        );

        try {
            $action->handle($cycleBlock);
        } catch (InvalidCycleTransitionException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $this->cycleTurnMessage($cycleBlock->cycleSlot->studyPlan, $cycleBlock),
        ]);

        return back();
    }
}
