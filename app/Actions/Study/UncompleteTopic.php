<?php

namespace App\Actions\Study;

use App\Models\Topic;
use Illuminate\Support\Facades\DB;

class UncompleteTopic
{
    /**
     * Desfaz a conclusão de uma tarefa — o clique errado no checkbox é o
     * caso comum, e não havia como voltar atrás.
     *
     * Apaga *todas* as revisões, não só as pendentes: o ciclo de repetição
     * espaçada nasce da conclusão (ver CompleteTopic), então desfazer a
     * conclusão desfaz o ciclo inteiro. Manter as já concluídas faria a
     * próxima conclusão criar uma segunda revisão de sequência 1, com duas
     * primeiras revisões no mesmo tópico. O que foi estudado de fato
     * continua registrado nas sessões, que não são tocadas aqui.
     */
    public function handle(Topic $topic): Topic
    {
        if (! $topic->isCompleted()) {
            return $topic;
        }

        DB::transaction(function () use ($topic): void {
            $topic->reviews()->delete();

            $topic->update(['completed_at' => null]);
        });

        return $topic->refresh();
    }
}
