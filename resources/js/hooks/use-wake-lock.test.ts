import { renderHook, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useWakeLock } from './use-wake-lock';

const sentinels: { released: boolean; release: ReturnType<typeof vi.fn> }[] =
    [];

const request = vi.fn(async () => {
    const sentinel = {
        released: false,
        release: vi.fn(async () => {
            sentinel.released = true;
        }),
    };

    sentinels.push(sentinel);

    return sentinel;
});

function setWakeLock(supported: boolean) {
    Object.defineProperty(navigator, 'wakeLock', {
        value: supported ? { request } : undefined,
        configurable: true,
    });
}

describe('useWakeLock', () => {
    beforeEach(() => {
        sentinels.length = 0;
        request.mockClear();
        setWakeLock(true);
    });

    afterEach(() => {
        Reflect.deleteProperty(navigator, 'wakeLock');
    });

    it('keeps the screen on while the cycle runs', async () => {
        renderHook(() => useWakeLock(true));

        await waitFor(() => expect(request).toHaveBeenCalledWith('screen'));
    });

    it('holds nothing while the session is paused', () => {
        renderHook(() => useWakeLock(false));

        expect(request).not.toHaveBeenCalled();
    });

    it('gives the screen back when the cycle ends', async () => {
        const { unmount } = renderHook(() => useWakeLock(true));

        await waitFor(() => expect(sentinels).toHaveLength(1));
        unmount();

        await waitFor(() => expect(sentinels[0].release).toHaveBeenCalled());
    });

    it('takes the lock again after the tab comes back', async () => {
        renderHook(() => useWakeLock(true));

        await waitFor(() => expect(sentinels).toHaveLength(1));

        // O navegador solta a trava sozinho quando a aba some; sem pedir
        // de novo, a tela apagaria no resto do ciclo.
        sentinels[0].released = true;
        document.dispatchEvent(new Event('visibilitychange'));

        await waitFor(() => expect(request).toHaveBeenCalledTimes(2));
    });

    it('does nothing where the browser has no wake lock', () => {
        setWakeLock(false);

        expect(() => renderHook(() => useWakeLock(true))).not.toThrow();
        expect(request).not.toHaveBeenCalled();
    });
});
