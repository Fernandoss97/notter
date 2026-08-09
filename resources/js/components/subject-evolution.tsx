import { ProgressBar } from '@/components/ui/progress-bar';
import { accuracyColor } from '@/lib/accuracy';
import { cn } from '@/lib/utils';
import type { SubjectEvolution } from '@/types/study';

/**
 * Evolução por disciplina, no formato em que quem estuda por trilha já
 * está acostumado a ler: uma linha por disciplina, a linha de totais no
 * fim.
 *
 * A coluna de desempenho é a que importa — é ela que responde "onde eu
 * estou errando" —, por isso ela ganha cor e as demais não.
 */
export function SubjectEvolutionTable({
    evolution,
}: {
    evolution: SubjectEvolution;
}) {
    const th =
        'border-b border-border px-3 py-2 text-left text-xs font-semibold tracking-wide text-muted-foreground uppercase';
    const td = 'border-b border-border px-3 py-1.5 text-sm text-foreground';

    return (
        <table className="w-full border-collapse overflow-hidden rounded-md bg-card">
            <thead>
                <tr>
                    <th className={th}>Disciplina</th>
                    <th className={cn(th, 'text-right')}>Tarefas</th>
                    <th className={th}>Evolução</th>
                    <th className={cn(th, 'text-right')}>Questões</th>
                    <th className={cn(th, 'text-right')}>Acertos</th>
                    <th className={cn(th, 'text-right')}>Desempenho</th>
                </tr>
            </thead>
            <tbody>
                {evolution.rows.map((row) => (
                    <tr
                        key={row.id}
                        className="transition-colors duration-fast ease-out hover:bg-muted/60"
                    >
                        <td className={td}>{row.name}</td>
                        <td className={cn(td, 'text-right font-mono tabular-nums')}>
                            {row.tasksDone}/{row.tasksTotal}
                        </td>
                        <td className={cn(td, 'w-40')}>
                            <ProgressBar value={row.progress} showLabel />
                        </td>
                        <td className={cn(td, 'text-right font-mono tabular-nums')}>
                            {row.exercises > 0 ? row.exercises : '—'}
                        </td>
                        <td className={cn(td, 'text-right font-mono tabular-nums')}>
                            {row.exercises > 0 ? row.correct : '—'}
                        </td>
                        <td
                            className={cn(
                                td,
                                'text-right font-mono tabular-nums',
                                accuracyColor(row.accuracy),
                            )}
                        >
                            {row.accuracy !== null ? `${row.accuracy}%` : '—'}
                        </td>
                    </tr>
                ))}
            </tbody>
            <tfoot>
                <tr className="font-semibold">
                    <td className={td}>Totais</td>
                    <td className={cn(td, 'text-right font-mono tabular-nums')}>
                        {evolution.totals.tasksDone}/
                        {evolution.totals.tasksTotal}
                    </td>
                    <td className={cn(td, 'w-40')}>
                        <ProgressBar
                            value={evolution.totals.progress}
                            showLabel
                        />
                    </td>
                    <td className={cn(td, 'text-right font-mono tabular-nums')}>
                        {evolution.totals.exercises > 0
                            ? evolution.totals.exercises
                            : '—'}
                    </td>
                    <td className={cn(td, 'text-right font-mono tabular-nums')}>
                        {evolution.totals.exercises > 0
                            ? evolution.totals.correct
                            : '—'}
                    </td>
                    <td
                        className={cn(
                            td,
                            'text-right font-mono tabular-nums',
                            accuracyColor(evolution.totals.accuracy),
                        )}
                    >
                        {evolution.totals.accuracy !== null
                            ? `${evolution.totals.accuracy}%`
                            : '—'}
                    </td>
                </tr>
            </tfoot>
        </table>
    );
}
