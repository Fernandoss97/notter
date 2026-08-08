import { useEffect, useState } from 'react';

export type LiveSession = {
    id: number;
    state: 'running' | 'paused' | 'completed';
    elapsedSeconds: number;
};

/**
 * Tempo decorrido de uma sessão de foco, ressincronizado a partir do
 * servidor (B7) sempre que a sessão ou seu elapsedSeconds mudam, e
 * interpolado localmente enquanto "running". O servidor nunca deixa de ser
 * a fonte da verdade — ver types/session.ts.
 *
 * A interpolação mede o relógio (`Date.now() - âncora`) em vez de contar
 * ticks. Numa tela onde a pessoa passa horas, a aba fica em segundo plano
 * e o navegador estrangula `setInterval` para ~1 disparo por minuto:
 * somar 1 por tick atrasaria o cronômetro indefinidamente. O intervalo só
 * decide *quando* reler o relógio; quem diz as horas é o relógio. Voltar
 * para a aba relê na hora, sem esperar o próximo tick.
 *
 * A âncora nasce dentro do efeito, não no render: `Date.now()` é impuro e
 * um render extra deslocaria o ponto de partida.
 */
export function useLiveElapsedSeconds(session: LiveSession | null): number {
    const [sync, setSync] = useState(() => ({
        id: session?.id,
        seconds: session?.elapsedSeconds ?? 0,
    }));
    const [displaySeconds, setDisplaySeconds] = useState(sync.seconds);

    // Comparação em render, não em efeito — ver
    // https://react.dev/learn/you-might-not-need-an-effect#adjusting-some-state-when-a-prop-changes.
    if (
        session?.id !== sync.id ||
        (session !== null && session.elapsedSeconds !== sync.seconds)
    ) {
        setSync({ id: session?.id, seconds: session?.elapsedSeconds ?? 0 });
        setDisplaySeconds(session?.elapsedSeconds ?? 0);
    }

    const running = session?.state === 'running';

    useEffect(() => {
        if (!running) {
            return;
        }

        const anchoredAt = Date.now();
        const tick = () =>
            setDisplaySeconds(
                sync.seconds + Math.floor((Date.now() - anchoredAt) / 1000),
            );

        const interval = setInterval(tick, 1000);
        document.addEventListener('visibilitychange', tick);

        return () => {
            clearInterval(interval);
            document.removeEventListener('visibilitychange', tick);
        };
    }, [running, sync]);

    return displaySeconds;
}
