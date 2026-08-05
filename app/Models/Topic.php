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
 * @property string $name
 * @property int $position
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'position', 'completed_at'])]
class Topic extends Model
{
    /** @use HasFactory<TopicFactory> */
    use HasFactory;

    protected $attributes = [
        'position' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'completed_at' => 'datetime',
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
