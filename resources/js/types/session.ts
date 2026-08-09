import type { FocusPhase, FocusSessionState } from '@/types/study';

/**
 * Snapshot compartilhado via Inertia para a SessionBar. O servidor é a
 * fonte da verdade (B7): `elapsedSeconds` é recalculado a cada visita a
 * partir de `started_at`/`paused_at` persistidos, nunca de um timer só do
 * cliente. O cliente apenas interpola a exibição entre requisições.
 *
 * `elapsedSeconds` é o relógio da *fase* corrente, não o total da sessão:
 * depois do primeiro ciclo o total ultrapassaria `plannedSeconds`.
 */
export type ActiveFocusSession = {
    id: number;
    subject: string;
    topic: string;
    state: FocusSessionState;
    phase: FocusPhase;
    completedCycles: number;
    elapsedSeconds: number;
    plannedSeconds: number;
};
