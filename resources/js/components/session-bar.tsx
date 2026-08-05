import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Dot } from '@/components/dot';
import { cn } from '@/lib/utils';

function formatElapsed(totalSeconds: number): string {
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

export function SessionBar() {
    const { activeSession } = usePage().props;

    const [elapsedSeconds, setElapsedSeconds] = useState(
        activeSession?.elapsedSeconds ?? 0,
    );

    // O servidor recalcula elapsedSeconds a cada visita/poll Inertia a
    // partir do started_at/paused_at persistido; sempre que esse valor (ou
    // a sessão) mudar, resincroniza o display antes de o tick local seguir
    // interpolando. Comparação em render, não em efeito — ver
    // https://react.dev/learn/you-might-not-need-an-effect#adjusting-some-state-when-a-prop-changes.
    const [synced, setSynced] = useState({
        id: activeSession?.id,
        elapsedSeconds: activeSession?.elapsedSeconds,
    });

    if (
        activeSession?.id !== synced.id ||
        activeSession?.elapsedSeconds !== synced.elapsedSeconds
    ) {
        setSynced({
            id: activeSession?.id,
            elapsedSeconds: activeSession?.elapsedSeconds,
        });
        setElapsedSeconds(activeSession?.elapsedSeconds ?? 0);
    }

    const sessionId = activeSession?.id;
    const sessionState = activeSession?.state;

    useEffect(() => {
        if (!sessionId || sessionState !== 'running') {
            return;
        }

        const interval = setInterval(() => {
            setElapsedSeconds((seconds) => seconds + 1);
        }, 1000);

        return () => clearInterval(interval);
    }, [sessionId, sessionState]);

    if (!activeSession) {
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
                'sticky top-0 z-10 flex h-11 items-center gap-4 border-b px-10',
                paused
                    ? 'border-pausa-200 bg-pausa-100'
                    : 'border-focus-200 bg-focus-100',
            )}
        >
            <span
                className={cn(
                    'flex items-center gap-2 text-xs font-bold tracking-wide uppercase',
                    paused ? 'text-pausa-600' : 'text-focus-500',
                )}
            >
                <Dot className={paused ? 'bg-pausa-500' : 'bg-focus-500'} />
                {paused ? 'Pausado' : 'Em foco'}
            </span>
            <span className="font-mono text-num-sm font-semibold text-foreground tabular-nums">
                {formatElapsed(elapsedSeconds)}
            </span>
            <span className="text-sm text-fg-2">
                {activeSession.subject} · {activeSession.topic}
            </span>
            <div className="h-1 max-w-[260px] flex-1 overflow-hidden rounded-full bg-foreground/10">
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
                href="/foco"
                className={cn(
                    'rounded-sm border px-3 py-1.5 text-xs font-semibold whitespace-nowrap',
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
