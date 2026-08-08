import { Button } from '@/components/ui/button';
import { ProgressBar } from '@/components/ui/progress-bar';
import { ReviewStatusBadge } from '@/components/ui/review-status-badge';
import { formatClock } from '@/lib/format-duration';
import { cn } from '@/lib/utils';
import type { FocusPhase, ReviewStatus } from '@/types/study';

const PHASE_LABEL: Record<FocusPhase, string> = {
    focus: 'Em foco',
    short_break: 'Intervalo',
    long_break: 'Intervalo longo',
};

const PHASE_DONE_LABEL: Record<FocusPhase, string> = {
    focus: 'Ciclo concluído',
    short_break: 'Intervalo terminado',
    long_break: 'Intervalo terminado',
};

export interface FocusTimerProps {
    phase: FocusPhase;
    paused: boolean;
    /** Decorrido da fase corrente, já interpolado no cliente. */
    elapsedSeconds: number;
    plannedSeconds: number;
    completedCycles: number;
    cyclesBeforeLongBreak: number;
    subjectLabel: string | null;
    topicLabel: string | null;
    reviewStatus?: ReviewStatus;
    zen?: boolean;
    onToggle: () => void;
    onAdvance: () => void;
    onSkipBreak: () => void;
}

/**
 * O cronômetro da tela de foco. Conta para baixo, que é o modelo mental
 * de um pomodoro ("faltam 26 minutos"), e mantém o decorrido como
 * informação secundária.
 */
export function FocusTimer({
    phase,
    paused,
    elapsedSeconds,
    plannedSeconds,
    completedCycles,
    cyclesBeforeLongBreak,
    subjectLabel,
    topicLabel,
    reviewStatus,
    zen = false,
    onToggle,
    onAdvance,
    onSkipBreak,
}: FocusTimerProps) {
    const onBreak = phase !== 'focus';
    const remainingSeconds = Math.max(0, plannedSeconds - elapsedSeconds);
    const finished = elapsedSeconds >= plannedSeconds;
    const progress = Math.min(
        100,
        Math.round((elapsedSeconds / plannedSeconds) * 100),
    );

    // O verde-água da pausa vale tanto para o intervalo quanto para o foco
    // pausado: os dois são "não está correndo tempo de estudo agora".
    const calm = onBreak || paused;
    const state = finished
        ? PHASE_DONE_LABEL[phase]
        : paused
          ? 'Pausado'
          : PHASE_LABEL[phase];

    // Pontos do ciclo pomodoro corrente — quantos foram fechados desde a
    // última pausa longa, não quantas sessões existiram hoje.
    const cyclesInRound = cyclesBeforeLongBreak || 1;
    const doneInRound = completedCycles % cyclesInRound;
    const cycles = Array.from(
        { length: cyclesInRound },
        (_, index) =>
            index <
            (completedCycles > 0 && doneInRound === 0
                ? cyclesInRound
                : doneInRound),
    );

    return (
        <div
            data-slot="focus-timer"
            data-phase={phase}
            className={cn(
                'flex flex-col items-center justify-center gap-6 rounded-md border p-10 text-center',
                'transition-colors duration-base ease-out',
                zen
                    ? 'border-transparent bg-transparent'
                    : 'border-border bg-card shadow-sm',
            )}
        >
            {(subjectLabel || topicLabel) && (
                <ReviewStatusBadge
                    status={reviewStatus ?? 'ontrack'}
                    label={[subjectLabel, topicLabel]
                        .filter(Boolean)
                        .join(' · ')}
                />
            )}

            <span
                // O rótulo é anunciado, o cronômetro não: um aria-live no
                // contador falaria a cada segundo.
                aria-live="polite"
                className={cn(
                    'flex items-center gap-2 text-xs font-bold tracking-wide uppercase',
                    'transition-colors duration-base ease-out',
                    calm ? 'text-pausa-600' : 'text-focus-500',
                )}
            >
                <span
                    className={cn(
                        'size-1.5 rounded-full transition-colors duration-base ease-out',
                        calm ? 'bg-pausa-500' : 'bg-focus-500',
                    )}
                />
                {state}
            </span>

            <span
                role="timer"
                aria-label={`Tempo restante: ${formatClock(remainingSeconds)}`}
                className={cn(
                    'font-mono text-foreground tabular-nums',
                    zen ? 'text-timer-lg' : 'text-timer',
                )}
            >
                {formatClock(remainingSeconds)}
            </span>

            <span className="text-xs text-muted-foreground">
                {formatClock(elapsedSeconds)} de{' '}
                {Math.round(plannedSeconds / 60)} min
            </span>

            <div className="w-full max-w-[320px]">
                <ProgressBar
                    value={progress}
                    color={calm ? 'pausa' : 'focus'}
                />
            </div>

            <div className="flex w-full max-w-[320px] items-center gap-2">
                {cycles.map((done, index) => (
                    <div
                        key={index}
                        className={cn(
                            'h-1.5 flex-1 rounded-full transition-colors duration-base ease-out',
                            done ? 'bg-focus-500' : 'bg-muted',
                        )}
                    />
                ))}
            </div>

            <div className="flex flex-wrap items-center justify-center gap-2">
                {finished ? (
                    <Button onClick={onAdvance}>
                        {onBreak ? 'Voltar ao foco' : 'Começar intervalo'}
                    </Button>
                ) : (
                    <Button
                        onClick={onToggle}
                        variant={paused ? 'default' : 'secondary'}
                    >
                        {paused ? 'Retomar' : 'Pausar'}
                    </Button>
                )}

                {onBreak && !finished && (
                    <Button variant="ghost" onClick={onSkipBreak}>
                        Pular intervalo
                    </Button>
                )}
            </div>
        </div>
    );
}
