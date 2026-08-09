import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { announcePhaseEnd } from '@/lib/focus-alerts';
import { advance as advancePhase } from '@/routes/focus';
import type { ActiveFocusSession } from '@/types/session';

/**
 * Fecha o ciclo quando o cronômetro chega ao fim.
 *
 * Vive na barra de sessão, não na tela de foco, porque a barra é a única
 * coisa montada em todas as telas: com o disparo na página, quem estivesse
 * no plano ou no histórico via o tempo zerar e nada acontecer até voltar
 * ao foco.
 *
 * O cliente só avisa que o tempo acabou — quem decide se a próxima fase
 * nasce rodando ou pausada é o servidor, a partir das preferências de
 * auto-start. Por isso não há condição alguma aqui.
 */
export function usePhaseEnd(
    session: ActiveFocusSession | null,
    elapsedSeconds: number,
) {
    // Um disparo por fase. As dependências do efeito já dão conta dos
    // ticks; o ref cobre o caso de `finished` oscilar dentro da mesma
    // fase — um avanço recusado ressincroniza o relógio para trás.
    const advancedFor = useRef<string | null>(null);

    const finished = session ? elapsedSeconds >= session.plannedSeconds : false;
    const phaseKey = session
        ? `${session.id}:${session.phase}:${session.completedCycles}`
        : null;
    const onBreak = session ? session.phase !== 'focus' : false;

    useEffect(() => {
        if (
            !finished ||
            phaseKey === null ||
            advancedFor.current === phaseKey
        ) {
            return;
        }

        advancedFor.current = phaseKey;

        announcePhaseEnd(
            onBreak ? 'Intervalo terminado' : 'Ciclo concluído',
            onBreak ? 'Hora de voltar ao foco.' : 'Hora de descansar.',
        );

        router.post(
            advancePhase().url,
            {},
            { preserveScroll: true, showProgress: false },
        );
        // `onBreak` deriva da fase, que já está em `phaseKey`.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [finished, phaseKey]);

    return finished;
}
