export function formatDurationMinutes(totalMinutes: number): string {
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;

    return hours > 0 ? `${hours}h ${minutes}min` : `${minutes}min`;
}

/**
 * Um cronômetro em `mm:ss`, que ganha o campo de horas só quando precisa.
 * Uma sessão longa não pode virar `180:23` — era o que a barra de sessão
 * fazia com a sua própria cópia desta função.
 */
export function formatClock(totalSeconds: number): string {
    const safeSeconds = Math.max(0, Math.floor(totalSeconds));
    const hours = Math.floor(safeSeconds / 3600);
    const minutes = Math.floor((safeSeconds % 3600) / 60);
    const seconds = safeSeconds % 60;
    const mm = String(minutes).padStart(2, '0');
    const ss = String(seconds).padStart(2, '0');

    return hours > 0 ? `${hours}:${mm}:${ss}` : `${mm}:${ss}`;
}
