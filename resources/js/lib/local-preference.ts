import { useSyncExternalStore } from 'react';

/**
 * Uma preferência de exibição por dispositivo: guardada no `localStorage`
 * e compartilhada por todos os componentes montados, no mesmo padrão de
 * store externo de `use-appearance`.
 *
 * Existe porque essas preferências não valem só na tela que as oferece —
 * esconder o tempo tem de valer também na barra de sessão e no título da
 * aba, que vivem em outra árvore. Um store de módulo é o que permite isso
 * sem passar a preferência de mão em mão pelo app inteiro.
 *
 * O que aparece no primeiro paint precisa de `cookie`: a página é
 * renderizada no servidor, e o servidor não tem `localStorage`. Sem ele o
 * HTML sai com o valor padrão e a preferência só entra em vigor depois da
 * hidratação — o tempo escondido apareceria por um instante a cada visita.
 * O cookie deixa o servidor saber, e `usePreference(…, serverValue)`
 * recebe de volta o que ele soube. Ver `HandleInertiaRequests::share`.
 *
 * O store não expõe o hook como método seu: `preferencia.use()` é uma
 * chamada de hook por propriedade, que o React Compiler confunde com o
 * `use()` do React 19 e reordena — o app quebrava ao montar os efeitos.
 * Quem lê é a função `usePreference` aqui embaixo.
 */
export type LocalPreference<T> = {
    subscribe: (callback: () => void) => () => void;
    getSnapshot: () => T;
    get: () => T;
    set: (value: T) => void;
    fallback: T;
};

/**
 * Lê uma preferência. `serverValue` é o que o servidor usou para montar o
 * HTML — passe-o sempre que a preferência afetar o primeiro paint, ou o
 * React hidrata com um valor diferente do que já está na tela.
 */
export function usePreference<T>(
    preference: LocalPreference<T>,
    serverValue?: T,
): readonly [T, (value: T) => void] {
    const value = useSyncExternalStore(
        preference.subscribe,
        preference.getSnapshot,
        () => (serverValue === undefined ? preference.fallback : serverValue),
    );

    return [value, preference.set] as const;
}

const COOKIE_MAX_AGE = 365 * 24 * 60 * 60;

export function createLocalPreference<T>({
    key,
    cookie,
    fallback,
    parse,
    serialize,
}: {
    key: string;
    /** Nome do cookie espelho, para as preferências que o servidor precisa ler. */
    cookie?: string;
    fallback: T;
    /** Devolve `null` para o que estiver guardado e não for mais válido. */
    parse: (stored: string) => T | null;
    serialize: (value: T) => string;
}): LocalPreference<T> {
    const listeners = new Set<() => void>();

    let current: T = fallback;
    let hydrated = false;

    function readStored(): T {
        if (typeof window === 'undefined') {
            return fallback;
        }

        const stored = localStorage.getItem(key);

        return (stored === null ? null : parse(stored)) ?? fallback;
    }

    function getSnapshot(): T {
        // A leitura acontece uma vez só: `getSnapshot` roda a cada render
        // e precisa devolver um valor estável, ou o React reagenda o
        // render indefinidamente.
        if (!hydrated) {
            hydrated = true;
            current = readStored();
        }

        return current;
    }

    function subscribe(callback: () => void): () => void {
        listeners.add(callback);

        return () => {
            listeners.delete(callback);
        };
    }

    function set(value: T): void {
        hydrated = true;
        current = value;

        if (typeof window !== 'undefined') {
            const stored = serialize(value);

            localStorage.setItem(key, stored);

            if (cookie) {
                document.cookie = `${cookie}=${stored};path=/;max-age=${COOKIE_MAX_AGE};SameSite=Lax`;
            }
        }

        listeners.forEach((listener) => listener());
    }

    return { subscribe, getSnapshot, get: getSnapshot, set, fallback };
}
