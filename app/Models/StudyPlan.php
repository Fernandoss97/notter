<?php

namespace App\Models;

use App\Models\Enums\StudyPlanType;
use Database\Factories\StudyPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property StudyPlanType $type
 * @property bool $is_active
 * @property int $weekly_goal_minutes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'type', 'is_active', 'weekly_goal_minutes'])]
class StudyPlan extends Model
{
    /** @use HasFactory<StudyPlanFactory> */
    use HasFactory;

    protected $attributes = [
        'is_active' => false,
        'weekly_goal_minutes' => 1200,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StudyPlanType::class,
            'is_active' => 'boolean',
            'weekly_goal_minutes' => 'integer',
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
     * @return HasMany<Subject, $this>
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class)->orderBy('position');
    }

    /**
     * @return HasMany<StudySession, $this>
     */
    public function studySessions(): HasMany
    {
        return $this->hasMany(StudySession::class);
    }
}
