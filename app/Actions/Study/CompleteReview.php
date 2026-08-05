<?php

namespace App\Actions\Study;

use App\Models\Review;
use Illuminate\Support\Facades\DB;

class CompleteReview
{
    /**
     * Marca a revisão como concluída e agenda a próxima da sequência fixa
     * (C1: 1/3/7/14/30 dias). Não cria uma próxima revisão depois da 5ª —
     * o ciclo de revisão do tópico termina ali.
     */
    public function handle(Review $review): Review
    {
        if ($review->completed_at !== null) {
            return $review;
        }

        DB::transaction(function () use ($review): void {
            $review->update(['completed_at' => now()]);

            $nextSequence = $review->sequence + 1;
            $intervals = Review::INTERVAL_DAYS;

            if ($nextSequence <= count($intervals)) {
                $review->topic->reviews()->create([
                    'sequence' => $nextSequence,
                    'due_on' => now()->addDays($intervals[$nextSequence - 1])->toDateString(),
                ]);
            }
        });

        return $review->refresh();
    }
}
