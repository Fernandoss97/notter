import type { FocusPhase } from '@/types/study';

/**
 * O aviso visual de fim de ciclo.
 *
 * Ele precisa de um store próprio porque o instante do fim não sobrevive
 * a um render: quem detecta é a barra de sessão, que no mesmo efeito já
 * pede o avanço ao servidor — quando a resposta chega, a sessão já está
 * na fase seguinte e não há mais nada dizendo "acabou agora". O store
 * guarda esse fato até alguém reagir a ele, e permite que a barra avise e
 * a tela de foco mostre, sem uma delas ter de saber da outra.
 */
export type PhaseAlert = {
    /** A fase que *terminou*, não a que começou. */
    phase: FocusPhase;
    /** Momento do disparo — troca a identidade do alerta a cada ciclo. */
    at: number;
};

let current: PhaseAlert | null = null;

const listeners = new Set<() => void>();

export function signalPhaseEnd(phase: FocusPhase): void {
    current = { phase, at: Date.now() };
    listeners.forEach((listener) => listener());
}

export function clearPhaseAlert(): void {
    if (!current) {
        return;
    }

    current = null;
    listeners.forEach((listener) => listener());
}

export function subscribePhaseAlert(callback: () => void): () => void {
    listeners.add(callback);

    return () => {
        listeners.delete(callback);
    };
}

export function getPhaseAlert(): PhaseAlert | null {
    return current;
}

export function phaseEndTitle(phase: FocusPhase): string {
    return phase === 'focus' ? 'Ciclo concluído' : 'Intervalo terminado';
}

export function phaseEndBody(phase: FocusPhase): string {
    return phase === 'focus' ? 'Hora de descansar.' : 'Hora de voltar ao foco.';
}
