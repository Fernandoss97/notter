<?php

namespace App\Models;

use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $topic_id
 * @property int $sequence
 * @property Carbon $due_on
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['sequence', 'due_on', 'completed_at'])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    /**
     * Intervalos fixos (dias) da revisão espaçada: 1ª revisão 1 dia após a
     * conclusão do tópico, 2ª 3 dias após a 1ª, etc. Intervalos
     * configuráveis por usuário (C3) ficaram fora do MVP.
     *
     * @var array<int, int>
     */
    public const INTERVAL_DAYS = [1, 3, 7, 14, 30];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'due_on' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Topic, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    /**
     * Revisões que pertencem ao usuário. `Review` está a três níveis do
     * dono (topic -> subject -> studyPlan -> user), fora do alcance de um
     * hasManyThrough, então o escopo é a única forma de nunca esquecer
     * essa cadeia ao buscar ou autorizar uma revisão.
     *
     * @param  Builder<Review>  $query
     */
    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        $query->whereHas(
            'topic.subject.studyPlan',
            fn (Builder $plan) => $plan->where('user_id', $user->id)
        );
    }

    /**
     * Revisões pendentes cujo prazo já chegou (vencidas ou para hoje).
     *
     * @param  Builder<Review>  $query
     */
    #[Scope]
    protected function due(Builder $query): void
    {
        $query->whereNull('completed_at')->whereDate('due_on', '<=', today());
    }
}
