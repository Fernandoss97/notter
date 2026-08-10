<?php

namespace App\Models;

use App\Models\Enums\SubjectMode;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $study_plan_id
 * @property string $name
 * @property SubjectMode $mode
 * @property int|null $goal_minutes
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'mode', 'goal_minutes', 'position'])]
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory;

    /**
     * Quantas matérias em primeiro contato o ciclo comporta bem (§4 da
     * spec). Não é um limite — é o ponto a partir do qual a tela avisa que
     * teoria nova demais ao mesmo tempo é o começo do ciclo inchado.
     */
    public const RECOMMENDED_READING_SUBJECTS = 6;

    protected $attributes = [
        'mode' => 'reading',
        'position' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => SubjectMode::class,
            'goal_minutes' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<StudyPlan, $this>
     */
    public function studyPlan(): BelongsTo
    {
        return $this->belongsTo(StudyPlan::class);
    }

    /**
     * A ordem é a da trilha, não a de criação: `position` é a sequência
     * dentro do plano inteiro, então uma tarefa de um ciclo anterior vem
     * antes mesmo tendo sido cadastrada depois.
     *
     * @return HasMany<Topic, $this>
     */
    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class)->orderBy('cycle')->orderBy('position');
    }

    /**
     * A cadeira da disciplina no rodízio, se ela estiver no ciclo. É
     * `hasOne` porque uma disciplina ocupa no máximo uma cadeira por
     * plano: peso maior se expressa em duração de bloco, não em repetição.
     *
     * @return HasOne<CycleSlot, $this>
     */
    public function cycleSlot(): HasOne
    {
        return $this->hasOne(CycleSlot::class);
    }

    /**
     * @return HasMany<StudySession, $this>
     */
    public function studySessions(): HasMany
    {
        return $this->hasMany(StudySession::class);
    }
}
