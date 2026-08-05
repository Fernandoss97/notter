<?php

namespace App\Models;

use App\Models\Enums\ActivityType;
use App\Models\Enums\FocusSessionState;
use Database\Factories\StudySessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $study_plan_id
 * @property int|null $subject_id
 * @property int|null $topic_id
 * @property ActivityType|null $activity_type
 * @property FocusSessionState $state
 * @property int $planned_seconds
 * @property int $accumulated_seconds
 * @property Carbon $started_at
 * @property Carbon|null $resumed_at
 * @property Carbon|null $ended_at
 * @property int|null $exercises_total
 * @property int|null $exercises_correct
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id', 'study_plan_id', 'subject_id', 'topic_id', 'activity_type', 'state',
    'planned_seconds', 'accumulated_seconds', 'started_at', 'resumed_at', 'ended_at',
    'exercises_total', 'exercises_correct',
])]
class StudySession extends Model
{
    /** @use HasFactory<StudySessionFactory> */
    use HasFactory;

    protected $attributes = [
        'state' => 'running',
        'accumulated_seconds' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activity_type' => ActivityType::class,
            'state' => FocusSessionState::class,
            'planned_seconds' => 'integer',
            'accumulated_seconds' => 'integer',
            'started_at' => 'datetime',
            'resumed_at' => 'datetime',
            'ended_at' => 'datetime',
            'exercises_total' => 'integer',
            'exercises_correct' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<StudyPlan, $this>
     */
    public function studyPlan(): BelongsTo
    {
        return $this->belongsTo(StudyPlan::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<Topic, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    /**
     * Tempo decorrido recalculado a partir do estado persistido (B7): o
     * cliente nunca é a fonte da verdade, só interpola visualmente entre
     * requisições (ver resources/js/components/session-bar.tsx).
     */
    public function elapsedSeconds(): int
    {
        $liveSeconds = $this->state === FocusSessionState::Running && $this->resumed_at
            ? (int) $this->resumed_at->diffInSeconds(now())
            : 0;

        return $this->accumulated_seconds + $liveSeconds;
    }

    public function accuracyPercent(): ?int
    {
        if (! $this->exercises_total) {
            return null;
        }

        return (int) round(($this->exercises_correct ?? 0) / $this->exercises_total * 100);
    }
}
