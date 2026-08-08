import { formatDurationMinutes } from '@/lib/format-duration';
import { cn } from '@/lib/utils';
import type { RecentDay } from '@/types/study';

const PLOT_HEIGHT = 120;

/**
 * Minutos estudados em cada um dos últimos sete dias.
 *
 * Série única, então não há legenda: o título do painel já nomeia o que
 * as barras medem. A meta diária entra como linha de referência porque a
 * pergunta que o gráfico responde não é "quanto estudei" e sim "bati o
 * dia" — sem a régua, sete alturas soltas não respondem nada.
 *
 * Dias sem estudo aparecem como coluna vazia: a lacuna é justamente a
 * informação, e omitir a barra a esconderia.
 */
export function WeeklyBars({
    days,
    goalMinutes,
}: {
    days: RecentDay[];
    goalMinutes: number;
}) {
    const busiest = Math.max(...days.map((day) => day.minutes), 0);
    // A escala acomoda a meta para que a linha de referência caiba mesmo
    // numa semana fraca — sem isso ela sairia do topo do gráfico.
    const ceiling = Math.max(busiest, goalMinutes, 1);
    const goalOffset = goalMinutes > 0 ? (goalMinutes / ceiling) * 100 : null;

    return (
        <div className="flex flex-col gap-2">
            <div
                className="relative flex items-end gap-0.5"
                style={{ height: PLOT_HEIGHT }}
            >
                {goalOffset !== null && goalOffset <= 100 && (
                    <div
                        aria-hidden
                        className="absolute inset-x-0 border-t border-dashed border-border-strong"
                        style={{ bottom: `${goalOffset}%` }}
                    />
                )}

                <ul className="flex h-full flex-1 items-end gap-0.5">
                    {days.map((day, index) => {
                        const isToday = index === days.length - 1;
                        const height =
                            day.minutes > 0
                                ? Math.max((day.minutes / ceiling) * 100, 2)
                                : 0;

                        return (
                            <li
                                key={day.date}
                                // Sete pontos: um aria-label por barra dá
                                // acesso aos números sem uma tabela à parte.
                                aria-label={`${day.weekday}, ${day.date}: ${formatDurationMinutes(day.minutes)}`}
                                title={`${day.date} · ${formatDurationMinutes(day.minutes)}`}
                                className="flex h-full flex-1 items-end"
                            >
                                <div
                                    className={cn(
                                        'w-full rounded-t-[4px] transition-[height] duration-base ease-out',
                                        isToday
                                            ? 'bg-focus-500'
                                            : 'bg-focus-400/60',
                                    )}
                                    style={{ height: `${height}%` }}
                                />
                            </li>
                        );
                    })}
                </ul>
            </div>

            <div className="flex gap-0.5">
                {days.map((day, index) => (
                    <span
                        key={day.date}
                        className={cn(
                            'flex-1 text-center text-xs',
                            index === days.length - 1
                                ? 'font-semibold text-foreground'
                                : 'text-muted-foreground',
                        )}
                    >
                        {day.weekday}
                    </span>
                ))}
            </div>

            {goalMinutes > 0 && (
                <p className="text-xs text-muted-foreground">
                    Linha tracejada: meta diária de{' '}
                    {formatDurationMinutes(goalMinutes)}.
                </p>
            )}
        </div>
    );
}
