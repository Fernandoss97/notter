<?php

namespace App\Models;

use App\Models\Enums\ActivityType;
use App\Models\Enums\FocusPhase;
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
 * @property int|null $cycle_block_id
 * @property ActivityType|null $activity_type
 * @property FocusSessionState $state
 * @property FocusPhase $phase
 * @property int $planned_seconds
 * @property int $accumulated_seconds
 * @property int $phase_accumulated_seconds
 * @property int $break_seconds
 * @property int $completed_cycles
 * @property Carbon $started_at
 * @property Carbon|null $resumed_at
 * @property Carbon|null $ended_at
 * @property int|null $exercises_total
 * @property int|null $exercises_correct
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id', 'study_plan_id', 'subject_id', 'topic_id', 'cycle_block_id', 'activity_type', 'state', 'phase',
    'planned_seconds', 'accumulated_seconds', 'phase_accumulated_seconds', 'break_seconds',
    'completed_cycles', 'started_at', 'resumed_at', 'ended_at',
    'exercises_total', 'exercises_correct', 'notes',
])]
class StudySession extends Model
{
    /** @use HasFactory<StudySessionFactory> */
    use HasFactory;

    /**
     * Folga depois do fim previsto da fase antes de considerar que a
     * sessão foi abandonada com a aba aberta.
     */
    public const STALE_GRACE_SECONDS = 3600;

    /**
     * Horas sem nenhuma interação antes de considerar abandonada uma
     * sessão pausada.
     */
    public const STALE_IDLE_HOURS = 12;

    protected $attributes = [
        'state' => 'running',
        'phase' => 'focus',
        'accumulated_seconds' => 0,
        'phase_accumulated_seconds' => 0,
        'break_seconds' => 0,
        'completed_cycles' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activity_type' => ActivityType::class,
            'state' => FocusSessionState::class,
            'phase' => FocusPhase::class,
            'planned_seconds' => 'integer',
            'accumulated_seconds' => 'integer',
            'phase_accumulated_seconds' => 'integer',
            'break_seconds' => 'integer',
            'completed_cycles' => 'integer',
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
     * O bloco do rodízio que esta sessão alimenta. Nulo é legítimo: quem
     * estuda fora do ciclo — ou não montou nenhum — continua registrando.
     *
     * @return BelongsTo<CycleBlock, $this>
     */
    public function cycleBlock(): BelongsTo
    {
        return $this->belongsTo(CycleBlock::class);
    }

    /**
     * O segmento ainda não congelado: só existe enquanto a sessão roda.
     * Pausar, trocar de fase e encerrar são justamente as operações que o
     * somam num acumulador e o zeram.
     */
    public function liveSeconds(): int
    {
        return $this->state === FocusSessionState::Running && $this->resumed_at
            ? (int) $this->resumed_at->diffInSeconds(now())
            : 0;
    }

    /**
     * Tempo decorrido recalculado a partir do estado persistido (B7): o
     * cliente nunca é a fonte da verdade, só interpola visualmente entre
     * requisições (ver resources/js/components/session-bar.tsx).
     *
     * Conta apenas o tempo de *foco* — é o número que alimenta dashboard e
     * histórico. O tempo de intervalo vive em breakElapsedSeconds().
     */
    public function elapsedSeconds(): int
    {
        return $this->accumulated_seconds + $this->liveFocusSeconds();
    }

    /**
     * A parte ainda não congelada do tempo de *foco*. É o que falta somar
     * a um total já persistido para ele não travar enquanto o cronômetro
     * roda — o bloco do ciclo depende disso para mostrar progresso vivo.
     */
    public function liveFocusSeconds(): int
    {
        return $this->phase === FocusPhase::Focus ? $this->liveSeconds() : 0;
    }

    public function breakElapsedSeconds(): int
    {
        return $this->break_seconds
            + ($this->phase->isBreak() ? $this->liveSeconds() : 0);
    }

    /**
     * O relógio que a tela mostra: quanto já correu da fase corrente. Zera
     * a cada transição, para o segundo pomodoro não começar em 50:00.
     */
    public function phaseElapsedSeconds(): int
    {
        return $this->phase_accumulated_seconds + $this->liveSeconds();
    }

    public function remainingSeconds(): int
    {
        return max(0, $this->planned_seconds - $this->phaseElapsedSeconds());
    }

    public function isPhaseExpired(): bool
    {
        return $this->phaseElapsedSeconds() >= $this->planned_seconds;
    }

    /**
     * Quanto do segmento vivo ainda cabe dentro da duração planejada da
     * fase. É o teto usado ao encerrar uma sessão abandonada: quem deixou
     * a aba aberta a noite toda não estudou a noite toda.
     */
    public function liveSecondsWithinPlan(): int
    {
        return min(
            $this->liveSeconds(),
            max(0, $this->planned_seconds - $this->phase_accumulated_seconds)
        );
    }

    /**
     * O tempo que conta como estudado, conforme a preferência do usuário
     * de descontar (ou não) os intervalos.
     */
    public function recordedSeconds(bool $excludeBreaks = false): int
    {
        return $this->elapsedSeconds()
            + ($excludeBreaks ? 0 : $this->breakElapsedSeconds());
    }

    /**
     * Sessão abandonada: ninguém a encerrou e ela deixou de refletir a
     * realidade. Rodando, o corte é a fase estourar com folga; pausada,
     * é ficar intocada tempo demais — `started_at` não serve como régua
     * porque uma sessão iniciada 23h50 não está velha à meia-noite.
     */
    public function isStale(): bool
    {
        if ($this->state === FocusSessionState::Completed) {
            return false;
        }

        if ($this->state === FocusSessionState::Paused) {
            return $this->updated_at?->isBefore(now()->subHours(self::STALE_IDLE_HOURS)) ?? false;
        }

        return $this->phaseElapsedSeconds() > $this->planned_seconds + self::STALE_GRACE_SECONDS;
    }

    public function accuracyPercent(): ?int
    {
        if (! $this->exercises_total) {
            return null;
        }

        return (int) round(($this->exercises_correct ?? 0) / $this->exercises_total * 100);
    }
}
