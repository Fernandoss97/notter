import { act, renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * O store da cor zen é de módulo: cada teste recarrega o módulo para não
 * herdar a escolha do anterior.
 */
async function loadHook() {
    vi.resetModules();

    return (await import('./use-zen-theme')).useZenTheme;
}

describe('useZenTheme', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    it('starts on the default theme', async () => {
        const useZenTheme = await loadHook();
        const { result } = renderHook(() => useZenTheme());

        expect(result.current.zenTheme.id).toBe('coral');
    });

    it('remembers the theme chosen on this device', async () => {
        const useZenTheme = await loadHook();
        const { result } = renderHook(() => useZenTheme());

        act(() => result.current.setZenTheme('azul'));

        expect(result.current.zenTheme.id).toBe('azul');
        expect(localStorage.getItem('zen-theme')).toBe('azul');
    });

    it('reads back what an earlier session stored', async () => {
        localStorage.setItem('zen-theme', 'verde');

        const useZenTheme = await loadHook();
        const { result } = renderHook(() => useZenTheme());

        expect(result.current.zenTheme.id).toBe('verde');
    });

    it('ignores a stored theme that no longer exists', async () => {
        localStorage.setItem('zen-theme', 'turquesa-antigo');

        const useZenTheme = await loadHook();
        const { result } = renderHook(() => useZenTheme());

        expect(result.current.zenTheme.id).toBe('coral');
    });

    it('keeps every mounted consumer in sync', async () => {
        const useZenTheme = await loadHook();
        const first = renderHook(() => useZenTheme());
        const second = renderHook(() => useZenTheme());

        act(() => first.result.current.setZenTheme('magenta'));

        expect(second.result.current.zenTheme.id).toBe('magenta');
    });
});
