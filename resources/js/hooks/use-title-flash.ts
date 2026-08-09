import { useEffect } from 'react';

const FLASH_MS = 1200;

/**
 * Alterna o título da aba enquanto houver um aviso pendente.
 *
 * É o único canal que funciona com a aba em segundo plano e a notificação
 * do sistema negada — e, ao contrário dela, não precisa de permissão
 * nenhuma. O título base é relido a cada alternância porque a página pode
 * reescrevê-lo (o cronômetro faz isso a cada segundo): o que se guarda é
 * de onde voltar, não um texto fixo.
 */
export function useTitleFlash(text: string | null): void {
    useEffect(() => {
        if (!text || typeof document === 'undefined') {
            return;
        }

        let showingFlash = false;
        let base = document.title;

        const swap = () => {
            if (showingFlash) {
                document.title = base;
            } else {
                base = document.title;
                document.title = text;
            }

            showingFlash = !showingFlash;
        };

        swap();

        const interval = setInterval(swap, FLASH_MS);

        return () => {
            clearInterval(interval);

            if (showingFlash) {
                document.title = base;
            }
        };
    }, [text]);
}
