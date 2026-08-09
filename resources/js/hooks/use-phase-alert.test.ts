import { act, renderHook } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { clearPhaseAlert, signalPhaseEnd } from '@/lib/phase-alert';
import { usePhaseAlert } from './use-phase-alert';

describe('usePhaseAlert', () => {
    afterEach(() => {
        vi.useRealTimers();
        clearPhaseAlert();
    });

    it('has nothing to say until a phase ends', () => {
        const { result } = renderHook(() => usePhaseAlert('running'));

        expect(result.current).toBeNull();
    });

    it('announces the phase that ended, not the one that started', () => {
        const { result } = renderHook(() => usePhaseAlert('paused'));

        act(() => signalPhaseEnd('focus'));

        expect(result.current?.phase).toBe('focus');
    });

    it('holds the warning while the next phase waits to be started', () => {
        vi.useFakeTimers();
        const { result } = renderHook(() => usePhaseAlert('paused'));

        act(() => signalPhaseEnd('focus'));
        act(() => vi.advanceTimersByTime(60_000));

        // Auto-start desligado: quem precisa clicar em "começar" quase
        // sempre está longe da tela. O aviso espera.
        expect(result.current).not.toBeNull();
    });

    it('lets the warning go once the next phase is already running', () => {
        vi.useFakeTimers();
        const { result } = renderHook(() => usePhaseAlert('running'));

        act(() => signalPhaseEnd('short_break'));
        expect(result.current).not.toBeNull();

        act(() => vi.advanceTimersByTime(6000));

        expect(result.current).toBeNull();
    });

    it('drops the warning at the first sign of someone being there', () => {
        const { result } = renderHook(() => usePhaseAlert('paused'));

        act(() => signalPhaseEnd('focus'));
        act(() => {
            document.dispatchEvent(new Event('pointerdown'));
        });

        expect(result.current).toBeNull();
    });
});
