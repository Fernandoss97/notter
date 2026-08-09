import { Panel } from '@/components/panel';
import { ReviewStatusBadge } from '@/components/ui/review-status-badge';
import { cn } from '@/lib/utils';
import type { TrackPace } from '@/types/study';

/**
 * O prazo contra o ritmo. A conta que interessa a quem estuda para uma
 * data marcada não é quanto já andou, é se o passo de hoje chega lá — por
 * isso o número necessário e o número real aparecem lado a lado, e não um
 * percentual de conclusão.
 */
export function TrackPacePanel({ pace }: { pace: TrackPace }) {
    const finished = pace.tasksRemaining === 0 && pace.tasksTotal > 0;
    const examPassed = pace.daysUntilExam !== null && pace.daysUntilExam < 0;

    return (
        <Panel title="Prazo e ritmo">
            <div className="flex flex-wrap items-start justify-between gap-6">
                <Countdown pace={pace} examPassed={examPassed} />

                <div className="flex flex-col gap-1 text-sm">
                    <Line
                        label="Tarefas restantes"
                        value={`${pace.tasksRemaining} de ${pace.tasksTotal}`}
                    />
                    {pace.requiredPerWeek !== null && (
                        <Line
                            label="Ritmo necessário"
                            value={`${format(pace.requiredPerWeek)} tarefas/semana`}
                        />
                    )}
                    <Line
                        label="Seu ritmo"
                        value={`${format(pace.currentPerWeek)} tarefas/semana`}
                        hint="últimas 4 semanas"
                    />
                    {pace.projectedOn && (
                        <Line
                            label="Previsão de término"
                            value={pace.projectedOn}
                            hint="mantido o ritmo atual"
                        />
                    )}
                </div>
            </div>

            <p className="text-xs text-muted-foreground">
                {summary(pace, finished, examPassed)}
            </p>
        </Panel>
    );
}

function Countdown({
    pace,
    examPassed,
}: {
    pace: TrackPace;
    examPassed: boolean;
}) {
    if (pace.daysUntilExam === null) {
        return (
            <div className="flex flex-col">
                <span className="text-xs tracking-wide text-muted-foreground uppercase">
                    Sem data de prova
                </span>
                <span className="mt-1 text-sm text-fg-2">
                    Marque a data no plano para acompanhar o prazo.
                </span>
            </div>
        );
    }

    return (
        <div className="flex flex-col">
            <span className="text-xs tracking-wide text-muted-foreground uppercase">
                {examPassed ? 'Prova realizada' : 'Prova em'}
            </span>
            <span
                className={cn(
                    'text-3xl font-extrabold tracking-tight tabular-nums',
                    examPassed ? 'text-fg-2' : 'text-foreground',
                )}
            >
                {examPassed
                    ? pace.examOn
                    : `${pace.daysUntilExam} ${pace.daysUntilExam === 1 ? 'dia' : 'dias'}`}
            </span>
            {!examPassed && pace.examOn && (
                <span className="mt-0.5 text-xs text-muted-foreground">
                    {pace.examOn}
                </span>
            )}
            {pace.onTrack !== null && (
                <ReviewStatusBadge
                    status={pace.onTrack ? 'goalHit' : 'goalMiss'}
                    label={pace.onTrack ? 'No ritmo' : 'Abaixo do ritmo'}
                    className="mt-2"
                />
            )}
        </div>
    );
}

function Line({
    label,
    value,
    hint,
}: {
    label: string;
    value: string;
    hint?: string;
}) {
    return (
        <div className="flex items-baseline gap-2">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-mono text-foreground tabular-nums">
                {value}
            </span>
            {hint && (
                <span className="text-xs text-muted-foreground">({hint})</span>
            )}
        </div>
    );
}

/**
 * Números sem casa decimal inútil: 3 tarefas por semana, não 3,0.
 */
function format(value: number): string {
    return String(value).replace('.', ',');
}

function summary(
    pace: TrackPace,
    finished: boolean,
    examPassed: boolean,
): string {
    if (pace.tasksTotal === 0) {
        return 'A trilha ainda não tem tarefas. Importe ou adicione as primeiras para acompanhar o ritmo.';
    }

    if (finished) {
        return 'Todas as tarefas da trilha estão concluídas.';
    }

    if (examPassed) {
        return 'A data da prova já passou. Atualize o plano se houver uma nova.';
    }

    if (pace.requiredPerWeek === null) {
        return pace.projectedOn
            ? 'Sem data de prova, a previsão vem só do seu ritmo das últimas semanas.'
            : 'Conclua tarefas para o ritmo aparecer aqui.';
    }

    if (pace.onTrack) {
        return 'No ritmo necessário para terminar a trilha antes da prova.';
    }

    return 'O ritmo atual não termina a trilha antes da prova.';
}
