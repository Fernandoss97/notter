import { useEffect, useSyncExternalStore } from 'react';
import {
    clearPhaseAlert,
    getPhaseAlert,
    subscribePhaseAlert,
} from '@/lib/phase-alert';
import type { PhaseAlert } from '@/lib/phase-alert';
import type { FocusSessionState } from '@/types/study';

/**
 * Quanto tempo o aviso fica na tela quando a fase seguinte já começou
 * sozinha. Curto de propósito: nesse caso ele confirma uma transição que
 * está acontecendo, não chama ninguém de volta.
 */
const TRANSIENT_MS = 6000;

/**
 * O aviso visual de fim de ciclo, com duas vidas diferentes conforme o
 * auto-start:
 *
 * - fase seguinte já rodando: a pessoa está aqui e o ciclo seguiu
 *   sozinho. O aviso é confirmação e sai depois de alguns segundos.
 * - fase seguinte pausada (auto-start desligado): o timer está parado
 *   esperando um clique, e quem precisa desse clique quase sempre está
 *   longe da tela. O aviso fica até alguém aparecer.
 *
 * Em ambos os casos qualquer interação encerra o aviso: se houve clique
 * ou tecla, ele já cumpriu o que tinha para fazer.
 */
export function usePhaseAlert(
    sessionState: FocusSessionState | null | undefined,
): PhaseAlert | null {
    const alert = useSyncExternalStore(
        subscribePhaseAlert,
        getPhaseAlert,
        () => null,
    );

    const transient = alert !== null && sessionState === 'running';

    useEffect(() => {
        if (!transient) {
            return;
        }

        const timeout = setTimeout(clearPhaseAlert, TRANSIENT_MS);

        return () => clearTimeout(timeout);
    }, [transient, alert?.at]);

    useEffect(() => {
        if (!alert) {
            return;
        }

        const dismiss = () => clearPhaseAlert();

        document.addEventListener('pointerdown', dismiss);
        document.addEventListener('keydown', dismiss);

        return () => {
            document.removeEventListener('pointerdown', dismiss);
            document.removeEventListener('keydown', dismiss);
        };
    }, [alert]);

    return alert;
}
