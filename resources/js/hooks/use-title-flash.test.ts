import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useTitleFlash } from './use-title-flash';

describe('useTitleFlash', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        document.title = 'Foco · 12:00';
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('leaves the title alone when there is nothing to announce', () => {
        renderHook(() => useTitleFlash(null));

        act(() => vi.advanceTimersByTime(5000));

        expect(document.title).toBe('Foco · 12:00');
    });

    it('alternates between the warning and the page title', () => {
        renderHook(() => useTitleFlash('⏰ Ciclo concluído'));

        expect(document.title).toBe('⏰ Ciclo concluído');

        act(() => vi.advanceTimersByTime(1200));
        expect(document.title).toBe('Foco · 12:00');

        act(() => vi.advanceTimersByTime(1200));
        expect(document.title).toBe('⏰ Ciclo concluído');
    });

    it('gives the title back when the warning is over', () => {
        const { unmount } = renderHook(() =>
            useTitleFlash('⏰ Ciclo concluído'),
        );

        unmount();

        expect(document.title).toBe('Foco · 12:00');
    });

    it('follows a title the page changed while flashing', () => {
        renderHook(() => useTitleFlash('⏰ Ciclo concluído'));

        // O cronômetro reescreve o título enquanto o aviso pisca: é dele
        // que o próximo retorno tem de partir, não de um texto guardado
        // no início.
        act(() => vi.advanceTimersByTime(1200));
        document.title = 'Intervalo · 09:59';
        act(() => vi.advanceTimersByTime(1200));
        act(() => vi.advanceTimersByTime(1200));

        expect(document.title).toBe('Intervalo · 09:59');
    });
});
