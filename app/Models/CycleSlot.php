<?php

namespace App\Models;

use App\Models\Enums\CycleSlotState;
use Database\Factories\CycleSlotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A cadeira de uma disciplina no rodízio do plano.
 *
 * @property int $id
 * @property int $study_plan_id
 * @property int $subject_id
 * @property int $position
 * @property int $block_minutes
 * @property Carbon|null $boost_until
 * @property CycleSlotState $state
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['subject_id', 'position', 'block_minutes', 'boost_until', 'state'])]
class CycleSlot extends Model
{
    /** @use HasFactory<CycleSlotFactory> */
    use HasFactory;

    /**
     * Os limites de duração de um bloco (§2 da spec): 60 a 90 minutos, com
     * exceção até 120 para matérias densas. O teto é regra; o intervalo
     * recomendado é só aviso — quem estuda Contabilidade sabe por que
     * estourou os 90.
     */
    public const MIN_BLOCK_MINUTES = 60;

    public const RECOMMENDED_MAX_BLOCK_MINUTES = 90;

    public const MAX_BLOCK_MINUTES = 120;

    /**
     * Quanto a imersão inicial acrescenta ao bloco enquanto vale (§4).
     *
     * O reforço é *somado* na hora de abrir a volta, e não gravado em
     * `block_minutes`. É o que faz a promessa se cumprir sozinha: quando a
     * data passa, o peso vai junto, sem depender de alguém lembrar de
     * desfazer. Gravado, ele seria de novo uma prioridade eterna — a
     * mesma coisa que aposentou `subjects.priority` neste projeto, só que
     * pior, porque sem selo na tela para denunciá-la.
     */
    public const BOOST_EXTRA_MINUTES = 30;

    protected $attributes = [
        'position' => 0,
        'block_minutes' => self::MIN_BLOCK_MINUTES,
        'state' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'block_minutes' => 'integer',
            'boost_until' => 'date',
            'state' => CycleSlotState::class,
        ];
    }

    /**
     * A disciplina ainda está na janela de imersão inicial (§4). A data é
     * inclusiva: o reforço vale até o fim do dia marcado.
     */
    public function isBoosted(): bool
    {
        return $this->boost_until !== null
            && ! $this->boost_until->isBefore(today());
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
     * @return HasMany<CycleBlock, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(CycleBlock::class)->orderBy('lap');
    }

    /**
     * As cadeiras que participam do rodízio. Uma cadeira pausada não some
     * — ela só deixa de receber bloco nas próximas voltas, que é como a
     * "reciclagem" do §4 enxuga o ciclo sem apagar nada.
     *
     * @param  Builder<CycleSlot>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('state', CycleSlotState::Active);
    }

    public function isActive(): bool
    {
        return $this->state === CycleSlotState::Active;
    }

    /**
     * Os minutos que o bloco vale hoje: a duração escolhida, mais o
     * reforço enquanto ele estiver de pé. `block_minutes` continua
     * significando "o que essa disciplina pede normalmente" — o reforço é
     * uma camada por cima, com prazo.
     */
    public function effectiveBlockMinutes(): int
    {
        return min(
            self::MAX_BLOCK_MINUTES,
            $this->block_minutes + ($this->isBoosted() ? self::BOOST_EXTRA_MINUTES : 0)
        );
    }

    public function targetSeconds(): int
    {
        return $this->effectiveBlockMinutes() * 60;
    }
}
