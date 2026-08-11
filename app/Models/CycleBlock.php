<?php

namespace App\Models;

use Database\Factories\CycleBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A execução de uma cadeira do rodízio numa volta.
 *
 * @property int $id
 * @property int $cycle_slot_id
 * @property int $lap
 * @property int $sequence
 * @property int $target_seconds
 * @property int $accumulated_seconds
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['lap', 'sequence', 'target_seconds', 'accumulated_seconds', 'completed_at'])]
class CycleBlock extends Model
{
    /** @use HasFactory<CycleBlockFactory> */
    use HasFactory;

    protected $attributes = [
        'sequence' => 1,
        'accumulated_seconds' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lap' => 'integer',
            'sequence' => 'integer',
            'target_seconds' => 'integer',
            'accumulated_seconds' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CycleSlot, $this>
     */
    public function cycleSlot(): BelongsTo
    {
        return $this->belongsTo(CycleSlot::class);
    }

    /**
     * @return HasMany<StudySession, $this>
     */
    public function studySessions(): HasMany
    {
        return $this->hasMany(StudySession::class);
    }

    /**
     * @param  Builder<CycleBlock>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * O bloco cumpriu o que prometeu. Não fecha sozinho: quem fecha é
     * CompleteCycleBlock, chamado quando a sessão devolve o tempo — assim
     * o fechamento acontece num ponto só, e o ponteiro do rodízio nunca
     * anda por efeito colateral de uma leitura.
     */
    public function hasReachedTarget(): bool
    {
        return $this->accumulated_seconds >= $this->target_seconds;
    }

    public function remainingSeconds(): int
    {
        return max(0, $this->target_seconds - $this->accumulated_seconds);
    }

    /**
     * Quanto do bloco já foi cumprido, em porcentagem inteira e limitada a
     * 100 — estourar o alvo é comum (o pomodoro não termina junto com o
     * bloco) e não deve vazar para a barra de progresso.
     */
    public function progressPercent(): int
    {
        if ($this->target_seconds === 0) {
            return 0;
        }

        return (int) min(100, round($this->accumulated_seconds / $this->target_seconds * 100));
    }
}
