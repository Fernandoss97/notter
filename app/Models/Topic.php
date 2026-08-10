<?php

namespace App\Models;

use Database\Factories\TopicFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $subject_id
 * @property int $cycle
 * @property string $name
 * @property int $position
 * @property Carbon|null $completed_at
 * @property int $exercises_total
 * @property int $exercises_correct
 * @property int $pages_read
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'cycle', 'position', 'completed_at', 'exercises_total', 'exercises_correct', 'pages_read'])]
class Topic extends Model
{
    /** @use HasFactory<TopicFactory> */
    use HasFactory;

    protected $attributes = [
        'cycle' => 0,
        'position' => 0,
        'exercises_total' => 0,
        'exercises_correct' => 0,
        'pages_read' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cycle' => 'integer',
            'position' => 'integer',
            'completed_at' => 'datetime',
            'exercises_total' => 'integer',
            'exercises_correct' => 'integer',
            'pages_read' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * @return HasMany<StudySession, $this>
     */
    public function studySessions(): HasMany
    {
        return $this->hasMany(StudySession::class);
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Percentual de acerto na tarefa. `null` quando não há questão
     * registrada — zero seria uma nota, não uma ausência.
     */
    public function accuracyPercent(): ?int
    {
        if ($this->exercises_total === 0) {
            return null;
        }

        return (int) round($this->exercises_correct / $this->exercises_total * 100);
    }

    /**
     * Soma o que foi resolvido numa sessão ao total da tarefa. A tarefa
     * acumula ao longo de várias sessões — e de registros feitos na mão.
     *
     * A soma acontece no banco, não em PHP: a instância que chega aqui foi
     * carregada quando a sessão começou e pode não saber de um registro
     * manual feito no meio do caminho.
     */
    public function addExercises(int $total, int $correct): void
    {
        $this->increment('exercises_total', $total);

        if ($correct > 0) {
            $this->increment('exercises_correct', $correct);
        }
    }

    /**
     * Soma as páginas lidas numa sessão ao total da tarefa. Mesmo raciocínio
     * de addExercises(): a tarefa acumula ao longo de várias sessões e de
     * registros feitos na mão, e a soma acontece no banco porque a instância
     * carregada no início da sessão pode estar desatualizada.
     */
    public function addPages(int $pages): void
    {
        if ($pages > 0) {
            $this->increment('pages_read', $pages);
        }
    }

    /**
     * A revisão pendente mais recente, se houver. Opera sobre a coleção
     * `reviews` (não a query builder) para não gerar N+1 quando o chamador
     * já fez eager load de `topics.reviews`.
     */
    public function pendingReview(): ?Review
    {
        return $this->reviews->whereNull('completed_at')->sortByDesc('sequence')->first();
    }

    /**
     * @return 'overdue'|'ontrack'|null
     */
    public function reviewStatus(): ?string
    {
        $pending = $this->pendingReview();

        if (! $pending) {
            return null;
        }

        return $pending->due_on->isPast() ? 'overdue' : 'ontrack';
    }
}
