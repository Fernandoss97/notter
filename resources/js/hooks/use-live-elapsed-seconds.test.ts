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
