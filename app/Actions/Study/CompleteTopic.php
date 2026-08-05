<?php

namespace App\Actions\Study;

use App\Models\Review;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;

class CompleteTopic
{
    /**
     * Marca o tópico como concluído e agenda a primeira revisão espaçada
     * (C1), 1 dia depois (Review::INTERVAL_DAYS[0]).
     */
    public function handle(Topic $topic): Topic
    {
        if ($topic->isCompleted()) {
            return $topic;
        }

        DB::transaction(function () use ($topic): void {
            $topic->update(['completed_at' => now()]);

            $topic->reviews()->create([
                'sequence' => 1,
                'due_on' => now()->addDays(Review::INTERVAL_DAYS[0])->toDateString(),
            ]);
        });

        return $topic->refresh();
    }
}
