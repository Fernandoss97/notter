import { Panel } from '@/components/panel';
import { formatDurationMinutes } from '@/lib/format-duration';
import { cn } from '@/lib/utils';
import type { CycleAdherence, CycleAdherenceRow } from '@/types/study';

/**
 * Quanto de fora do combinado uma disciplina precisa ficar para valer um
 * aviso. Abaixo disso é a variação normal de quem tem semanas diferentes;
 * acima, é padrão.
 */
const DRIFT_TOLERANCE = 5;

/**
 * O que o ciclo prometeu contra o que aconteceu.
 *
 * A comparação é entre fatias, não entre minutos: quem estudou metade do
 * planejado no mês cumpriu o rodízio se distribuiu essa metade na
 * proporção certa. Minutos absolutos só diriam que estudou pouco, coisa
 * que a meta semanal já diz logo acima.
 *
 * É a única tela que denuncia a disciplina sendo evitada — no plano, quem
 * foge de uma matéria simplesmente não conclui nada nela, e isso se
 * confunde com "ainda não cheguei lá".
 */
export function CycleAdherencePanel({ cycle }: { cycle: CycleAdherence }) {
    const avoided = cycle.rows
        .filter((row) => row.drift <= -DRIFT_TOLERANCE)
        .sort((a, b) => a.drift - b.drift);

    return (
        <Panel title="Aderência ao ciclo">
            <div className="flex flex-wrap items-baseline gap-x-6 gap-y-1 text-sm text-fg-2">
                <span>
                    <strong className="font-mono text-foreground tabular-nums">
                        {cycle.closedLaps}
                    </strong>{' '}
                    {cycle.closedLaps === 1 ? 'volta fechada' : 'voltas fechadas'}
                </span>
                <span>
                    <strong className="font-mono text-foreground tabular-nums">
                        {cycle.blocksDone}
                    </strong>{' '}
                    {cycle.blocksDone === 1
                        ? 'bloco concluído'
                        : 'blocos concluídos'}
                </span>
            </div>

            <div className="flex flex-col gap-2.5">
                {cycle.rows.map((row) => (
                    <AdherenceRow key={row.id} row={row} />
                ))}
            </div>

            <p className="text-xs text-muted-foreground">
                {avoided.length === 0
                    ? 'O tempo das últimas quatro semanas seguiu o que o ciclo pediu.'
                    : `Nas últimas quatro semanas, ${listNames(avoided)} ${avoided.length === 1 ? 'ficou' : 'ficaram'} com menos tempo do que o ciclo pede.`}
            </p>
        </Panel>
    );
}

/**
 * Duas barras sobrepostas seriam mais bonitas e menos legíveis: o que
 * importa aqui é a *diferença*, então ela vem escrita, e a barra só situa
 * a ordem de grandeza.
 */
function AdherenceRow({ row }: { row: CycleAdherenceRow }) {
    const off = Math.abs(row.drift) >= DRIFT_TOLERANCE;

    return (
        <div data-slot="cycle-adherence-row" className="flex flex-col gap-1">
            <div className="flex items-baseline justify-between gap-3 text-sm">
                <span className="truncate text-foreground">{row.name}</span>
                <span className="shrink-0 font-mono text-xs text-muted-foreground tabular-nums">
                    {row.actualPercent}% de {row.targetPercent}% ·{' '}
                    {formatDurationMinutes(row.minutes)}
                </span>
            </div>

            <div className="relative h-2 overflow-hidden rounded-full bg-muted">
                {/* O alvo é uma marca, não um preenchimento: ele diz onde a
                    barra deveria parar. */}
                <div
                    aria-hidden
                    className="absolute inset-y-0 w-px bg-border-strong"
                    style={{ left: `${Math.min(100, row.targetPercent)}%` }}
                />
                <div
                    role="progressbar"
                    aria-valuenow={row.actualPercent}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-label={`${row.name}: ${row.actualPercent}% do tempo, alvo ${row.targetPercent}%`}
                    className={cn(
                        'h-full w-full rounded-full transition-transform duration-base ease-out',
                        off && row.drift < 0
                            ? 'bg-status-warning'
                            : 'bg-focus-500',
                    )}
                    style={{
                        transform: `translateX(-${100 - Math.min(100, row.actualPercent)}%)`,
                    }}
                />
            </div>
        </div>
    );
}

function listNames(rows: CycleAdherenceRow[]): string {
    const names = rows.map((row) => row.name);

    if (names.length === 1) {
        return names[0];
    }

    return `${names.slice(0, -1).join(', ')} e ${names.at(-1)}`;
}
