import { act, renderHook } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { useLiveElapsedSeconds } from './use-live-elapsed-seconds';

describe('useLiveElapsedSeconds', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('returns 0 when there is no session', () => {
        const { result } = renderHook(() => useLiveElapsedSeconds(null));

        expect(result.current).toBe(0);
    });

    it('starts at the session elapsedSeconds', () => {
        const { result } = renderHook(() =>
            useLiveElapsedSeconds({
                id: 1,
                state: 'running',
                elapsedSeconds: 42,
            }),
        );

        expect(result.current).toBe(42);
    });

    it('ticks forward once a second while running', () => {
        vi.useFakeTimers();
        const { result } = renderHook(() =>
            useLiveElapsedSeconds({
                id: 1,
                state: 'running',
                elapsedSeconds: 0,
            }),
        );

        act(() => {
            vi.advanceTimersByTime(3000);
        });

        expect(result.current).toBe(3);
    });

    it('does not tick while paused', () => {
        vi.useFakeTimers();
        const { result } = renderHook(() =>
            useLiveElapsedSeconds({
                id: 1,
                state: 'paused',
                elapsedSeconds: 10,
            }),
        );

        act(() => {
            vi.advanceTimersByTime(5000);
        });

        expect(result.current).toBe(10);
    });

    it('resyncs when the server reports a new elapsedSeconds for the same session', () => {
        const { result, rerender } = renderHook(
            ({ elapsedSeconds }) =>
                useLiveElapsedSeconds({
                    id: 1,
                    state: 'paused',
                    elapsedSeconds,
                }),
            { initialProps: { elapsedSeconds: 10 } },
        );

        expect(result.current).toBe(10);

        rerender({ elapsedSeconds: 25 });

        expect(result.current).toBe(25);
    });

    it('keeps up with the wall clock when the tab throttles the interval', () => {
        vi.useFakeTimers();
        const { result } = renderHook(() =>
            useLiveElapsedSeconds({
                id: 1,
                state: 'running',
                elapsedSeconds: 0,
            }),
        );

        // Uma aba em segundo plano recebe ~1 disparo por minuto em vez de
        // 60. Adiantar o relógio sem disparar os timers e só então deixar
        // um tick passar reproduz exatamente isso: contar ticks daria 1s.
        act(() => {
            vi.setSystemTime(Date.now() + 60_000);
            vi.advanceTimersByTime(1000);
        });

        expect(result.current).toBe(61);
    });

    it('recomputes as soon as the tab becomes visible again', () => {
        vi.useFakeTimers();
        const { result } = renderHook(() =>
            useLiveElapsedSeconds({
                id: 1,
                state: 'running',
                elapsedSeconds: 0,
            }),
        );

        act(() => {
            vi.setSystemTime(Date.now() + 120_000);
            document.dispatchEvent(new Event('visibilitychange'));
        });

        expect(result.current).toBe(120);
    });

    it('schedules a read for the exact end of the phase', () => {
        vi.useFakeTimers();
        const setTimeoutSpy = vi.spyOn(globalThis, 'setTimeout');

        renderHook(() =>
            useLiveElapsedSeconds({
                id: 1,
                state: 'running',
                elapsedSeconds: 30,
                plannedSeconds: 90,
            }),
        );

        // O estrangulamento de aba em segundo plano não existe no jsdom,
        // então o que dá para verificar é o agendamento: sem ele o fim do
        // pomodoro esperaria o próximo disparo de um intervalo que, em
        // segundo plano, vem de minuto em minuto.
        expect(setTimeoutSpy).toHaveBeenCalledWith(
            expect.any(Function),
            60_050,
        );
    });

    it('schedules nothing extra for a phase that already overran', () => {
        vi.useFakeTimers();
        const setTimeoutSpy = vi.spyOn(globalThis, 'setTimeout');

        renderHook(() =>
            useLiveElapsedSeconds({
                id: 1,
                state: 'running',
                elapsedSeconds: 120,
                plannedSeconds: 90,
            }),
        );

        expect(setTimeoutSpy).not.toHaveBeenCalled();
    });

    it('recomputes when the window gets the focus back', () => {
        vi.useFakeTimers();
        const { result } = renderHook(() =>
            useLiveElapsedSeconds({
                id: 1,
                state: 'running',
                elapsedSeconds: 0,
            }),
        );

        // Aba visível atrás de outra janela: `visibilitychange` não
        // dispara, só o foco volta.
        act(() => {
            vi.setSystemTime(Date.now() + 45_000);
            window.dispatchEvent(new Event('focus'));
        });

        expect(result.current).toBe(45);
    });

    it('resets when switching to a different session', () => {
        const { result, rerender } = renderHook(
            ({ id, elapsedSeconds }) =>
                useLiveElapsedSeconds({ id, state: 'running', elapsedSeconds }),
            { initialProps: { id: 1, elapsedSeconds: 500 } },
        );

        expect(result.current).toBe(500);

        rerender({ id: 2, elapsedSeconds: 0 });

        expect(result.current).toBe(0);
    });
});
