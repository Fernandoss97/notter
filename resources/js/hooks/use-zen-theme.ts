import { useSyncExternalStore } from 'react';
import { DEFAULT_ZEN_THEME, findZenTheme } from '@/lib/zen-themes';
import type { ZenTheme, ZenThemeId } from '@/lib/zen-themes';

const STORAGE_KEY = 'zen-theme';

/**
 * A cor do modo zen é preferência de aparência por dispositivo, como o
 * tema claro/escuro de `use-appearance` — mesmo padrão de store externo.
 *
 * Sem cookie, ao contrário da aparência: o zen só existe depois de um
 * clique, então nada dele é renderizado no servidor e não há flash de
 * cor errada para evitar.
 */
const listeners = new Set<() => void>();

let currentThemeId: ZenThemeId = DEFAULT_ZEN_THEME;
let hydrated = false;

function readStored(): ZenThemeId {
    if (typeof window === 'undefined') {
        return DEFAULT_ZEN_THEME;
    }

    return findZenTheme(localStorage.getItem(STORAGE_KEY)).id;
}

function subscribe(callback: () => void): () => void {
    listeners.add(callback);

    return () => {
        listeners.delete(callback);
    };
}

function getSnapshot(): ZenThemeId {
    // A leitura do localStorage acontece uma vez, na primeira chamada do
    // cliente: `getSnapshot` roda a cada render e precisa devolver um
    // valor estável, ou o React reagenda o render indefinidamente.
    if (!hydrated) {
        hydrated = true;
        currentThemeId = readStored();
    }

    return currentThemeId;
}

export type UseZenThemeReturn = {
    readonly zenTheme: ZenTheme;
    readonly setZenTheme: (id: ZenThemeId) => void;
};

export function useZenTheme(): UseZenThemeReturn {
    const id = useSyncExternalStore(
        subscribe,
        getSnapshot,
        () => DEFAULT_ZEN_THEME,
    );

    const setZenTheme = (next: ZenThemeId): void => {
        currentThemeId = findZenTheme(next).id;

        if (typeof window !== 'undefined') {
            localStorage.setItem(STORAGE_KEY, currentThemeId);
        }

        listeners.forEach((listener) => listener());
    };

    return { zenTheme: findZenTheme(id), setZenTheme } as const;
}
