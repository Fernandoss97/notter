import { Link, usePage } from '@inertiajs/react';
import { Dot } from '@/components/dot';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useLiveElapsedSeconds } from '@/hooks/use-live-elapsed-seconds';
import { cn } from '@/lib/utils';
import { show as showFocus } from '@/routes/focus';

function formatElapsed(totalSeconds: number): string {
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

export function SessionBar() {
    const { activeSession } = usePage().props;
    const elapsedSeconds = useLiveElapsedSeconds(activeSession);
    const { isCurrentUrl } = useCurrentUrl();

    if (!activeSession || isCurrentUrl(showFocus())) {
        return null;
    }

    const paused = activeSession.state === 'paused';
    const progress = Math.min(
        100,
        Math.round((elapsedSeconds / activeSession.plannedSeconds) * 100),
    );

    return (
        <div
            data-slot="session-bar"
            className={cn(
                'sticky top-0 z-10 flex h-16 items-center gap-5 border-b px-10',
                paused
                    ? 'border-pausa-200 bg-pausa-100'
                    : 'border-focus-200 bg-focus-100',
            )}
        >
            <span
                className={cn(
                    'flex items-center gap-2 text-sm font-bold tracking-wide uppercase',
                    paused ? 'text-pausa-600' : 'text-focus-500',
                )}
            >
                <Dot
                    className={cn(
                        'size-2.5',
                        paused ? 'bg-pausa-500' : 'bg-focus-500',
                    )}
                />
                {paused ? 'Pausado' : 'Em foco'}
            </span>
            <span className="font-mono text-2xl font-semibold text-foreground tabular-nums">
                {formatElapsed(elapsedSeconds)}
            </span>
            <span className="text-base text-fg-2">
                {activeSession.subject} · {activeSession.topic}
            </span>
            <div className="h-1.5 max-w-[320px] flex-1 overflow-hidden rounded-full bg-foreground/10">
                <div
                    className={cn(
                        'h-full',
                        paused ? 'bg-pausa-500' : 'bg-focus-500',
                    )}
                    style={{ width: `${progress}%` }}
                />
            </div>
            <div className="flex-1" />
            <Link
                href={showFocus()}
                className={cn(
                    'rounded-sm border px-4 py-2 text-sm font-semibold whitespace-nowrap',
                    paused
                        ? 'border-pausa-500 text-pausa-600'
                        : 'border-focus-500 text-focus-500',
                )}
            >
                Abrir sessão
            </Link>
        </div>
    );
}
