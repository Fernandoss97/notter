import { useEffect, useRef } from 'react';

type HotkeyMap = Record<string, (() => void) | undefined>;

/**
 * Um listener no documento para os atalhos da tela de foco. Teclas soltas
 * (sem modificador) só fazem sentido numa tela como essa, onde não há
 * texto para digitar na maior parte do tempo — daí a guarda contra
 * campos de formulário, que de outro modo engoliriam a letra digitada.
 *
 * As chaves são `event.key` em minúsculas: `' '`, `'e'`, `'escape'`.
 */
export function useHotkeys(hotkeys: HotkeyMap, enabled = true) {
    // Ref para não reassinar o listener a cada render: os handlers são
    // recriados a cada frame do cronômetro.
    const latest = useRef(hotkeys);

    useEffect(() => {
        latest.current = hotkeys;
    });

    useEffect(() => {
        if (!enabled) {
            return;
        }

        function onKeyDown(event: KeyboardEvent) {
            if (event.ctrlKey || event.metaKey || event.altKey) {
                return;
            }

            const target = event.target as HTMLElement | null;

            if (
                target?.isContentEditable ||
                ['INPUT', 'TEXTAREA', 'SELECT'].includes(target?.tagName ?? '')
            ) {
                return;
            }

            const handler = latest.current[event.key.toLowerCase()];

            if (!handler) {
                return;
            }

            event.preventDefault();
            handler();
        }

        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    }, [enabled]);
}
