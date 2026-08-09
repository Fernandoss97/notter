import { Button } from '@/components/ui/button';
import { ProgressBar } from '@/components/ui/progress-bar';
import { ReviewStatusBadge } from '@/components/ui/review-status-badge';
import { formatClock } from '@/lib/format-duration';
import { phaseEndTitle } from '@/lib/phase-alert';
import { cn } from '@/lib/utils';
import type { FocusPhase, ReviewStatus } from '@/types/study';

const PHASE_LABEL: Record<FocusPhase, string> = {
    focus: 'Em foco',
    short_break: 'Intervalo',
    long_break: 'Intervalo longo',
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
    /**
     * A fase que acabou de terminar, enquanto o aviso estiver de pé. Vem
     * do store de `lib/phase-alert`, não da sessão: quando o aviso
     * aparece a sessão já está na fase seguinte.
     */
    alertPhase?: FocusPhase | null;
    onToggle: () => void;
    onAdvance: () => void;
    onSkipBreak: () => void;
    /** Estende a fase corrente. Ausente, o botão não aparece. */
    onExtend?: () => void;
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
    alertPhase = null,
    onToggle,
    onAdvance,
    onSkipBreak,
    onExtend,
}: FocusTimerProps) {
    const onBreak = phase !== 'focus';
    const remainingSeconds = Math.max(0, plannedSeconds - elapsedSeconds);
    const finished = elapsedSeconds >= plannedSeconds;
    // A fase nasceu pausada e ninguém a começou ainda — é o estado em que
    // o auto-start desligado deixa o ciclo seguinte. "Retomar" mentiria
    // sobre o que o botão faz.
    //
    // O aviso de fim de ciclo faz parte da condição justamente para não
    // confundir esse caso com quem pausou nos primeiros segundos: ali o
    // relógio também está em zero, mas a fase já tinha começado.
    const notStarted = alertPhase !== null && paused && elapsedSeconds === 0;
    const progress = Math.min(
        100,
        Math.round((elapsedSeconds / plannedSeconds) * 100),
    );

    // O verde-água da pausa vale tanto para o intervalo quanto para o foco
    // pausado: os dois são "não está correndo tempo de estudo agora".
    const calm = onBreak || paused;
    const alerting = alertPhase !== null || finished;
    const state = alertPhase
        ? phaseEndTitle(alertPhase)
        : finished
          ? phaseEndTitle(phase)
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
                        alerting && 'animate-pulse',
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
                        {notStarted
                            ? onBreak
                                ? 'Começar intervalo'
                                : 'Começar foco'
                            : paused
                              ? 'Retomar'
                              : 'Pausar'}
                    </Button>
                )}

                {onBreak && !finished && (
                    <Button variant="ghost" onClick={onSkipBreak}>
                        Pular intervalo
                    </Button>
                )}

                {/* Só no foco: esticar um intervalo é adiar o estudo, e o
                    ciclo pomodoro já tem "pular intervalo" para o caso de
                    querer voltar antes.

                    E só com o ciclo correndo: no instante em que o tempo
                    acaba, a barra de sessão já pediu o avanço. Esticar aí
                    faria o servidor recusar o avanço em voo — e o pedido
                    recusado não volta a ser tentado, deixando o ciclo sem
                    quem o feche. */}
                {onExtend && !onBreak && !finished && (
                    <Button variant="ghost" onClick={onExtend}>
                        +5 min
                    </Button>
                )}
            </div>
        </div>
    );
}
