import { useEffect, useState } from 'react';

export type LiveSession = {
    id: number;
    state: 'running' | 'paused' | 'completed';
    elapsedSeconds: number;
};

/**
 * Tempo decorrido de uma sessão de foco, ressincronizado a partir do
 * servidor (B7) sempre que a sessão ou seu elapsedSeconds mudam, e
 * interpolado localmente (tick de 1s) só enquanto "running". O servidor
 * nunca deixa de ser a fonte da verdade — ver types/session.ts.
 */
export function useLiveElapsedSeconds(session: LiveSession | null): number {
    const [elapsedSeconds, setElapsedSeconds] = useState(
        session?.elapsedSeconds ?? 0,
    );

    // Comparação em render, não em efeito — ver
    // https://react.dev/learn/you-might-not-need-an-effect#adjusting-some-state-when-a-prop-changes.
    const [synced, setSynced] = useState({
        id: session?.id,
        elapsedSeconds: session?.elapsedSeconds,
    });

    if (
        session?.id !== synced.id ||
        session?.elapsedSeconds !== synced.elapsedSeconds
    ) {
        setSynced({ id: session?.id, elapsedSeconds: session?.elapsedSeconds });
        setElapsedSeconds(session?.elapsedSeconds ?? 0);
    }

    const sessionId = session?.id;
    const sessionState = session?.state;

    useEffect(() => {
        if (!sessionId || sessionState !== 'running') {
            return;
        }

        const interval = setInterval(() => {
            setElapsedSeconds((seconds) => seconds + 1);
        }, 1000);

        return () => clearInterval(interval);
    }, [sessionId, sessionState]);

    return elapsedSeconds;
}
