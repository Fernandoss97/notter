import { cn } from '@/lib/utils';

export interface SessionRow {
    date: string;
    subject: string;
    duration: string;
    type: string;
    exercises: number | null;
    accuracy: number | null;
}

function accuracyColor(accuracy: number | null): string {
    if (accuracy === null) {
        return 'text-muted-foreground';
    }

    if (accuracy >= 70) {
        return 'text-status-success';
    }

    return accuracy >= 50 ? 'text-status-warning' : 'text-status-danger';
}

export function SessionsTable({ rows }: { rows: SessionRow[] }) {
    const th =
        'border-b border-border px-3 py-2 text-left text-xs font-semibold tracking-wide text-muted-foreground uppercase';
    const td = 'border-b border-border px-3 py-1.5 text-sm text-foreground';

    return (
        <table className="w-full border-collapse overflow-hidden rounded-md bg-card">
            <thead>
                <tr>
                    <th className={th}>Data</th>
                    <th className={th}>Disciplina</th>
                    <th className={th}>Duração</th>
                    <th className={th}>Tipo</th>
                    <th className={cn(th, 'text-right')}>Exercícios</th>
                    <th className={cn(th, 'text-right')}>Acerto</th>
                </tr>
            </thead>
            <tbody>
                {rows.map((row, index) => (
                    <tr
                        key={index}
                        className="transition-colors duration-fast ease-out hover:bg-muted/60"
                    >
                        <td className={cn(td, 'font-mono text-xs text-fg-2')}>
                            {row.date}
                        </td>
                        <td className={td}>{row.subject}</td>
                        <td className={cn(td, 'font-mono tabular-nums')}>
                            {row.duration}
                        </td>
                        <td className={cn(td, 'text-fg-2')}>{row.type}</td>
                        <td
                            className={cn(
                                td,
                                'text-right font-mono tabular-nums',
                            )}
                        >
                            {row.exercises ?? '—'}
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
        </table>
    );
}
