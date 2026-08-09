import { renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useFullscreen } from './use-fullscreen';

const requestFullscreen = vi.fn(async () => {
    setFullscreenElement(document.documentElement);
});

const exitFullscreen = vi.fn(async () => {
    setFullscreenElement(null);
});

function setFullscreenElement(element: Element | null) {
    Object.defineProperty(document, 'fullscreenElement', {
        value: element,
        configurable: true,
    });
}

describe('useFullscreen', () => {
    beforeEach(() => {
        requestFullscreen.mockClear();
        exitFullscreen.mockClear();
        setFullscreenElement(null);
        document.documentElement.requestFullscreen = requestFullscreen;
        document.exitFullscreen = exitFullscreen;
    });

    it('takes the whole screen when the zen mode opens', () => {
        renderHook(() => useFullscreen(true));

        expect(requestFullscreen).toHaveBeenCalledOnce();
    });

    it('stays out of the way while the zen mode is closed', () => {
        renderHook(() => useFullscreen(false));

        expect(requestFullscreen).not.toHaveBeenCalled();
    });

    it('gives the screen back when the zen mode closes', () => {
        const { rerender } = renderHook(({ zen }) => useFullscreen(zen), {
            initialProps: { zen: true },
        });

        rerender({ zen: false });

        expect(exitFullscreen).toHaveBeenCalledOnce();
    });

    it('reports the escape hatch the browser owns', () => {
        const onExit = vi.fn();
        renderHook(() => useFullscreen(true, onExit));

        // Em tela cheia o Esc é consumido pelo navegador e nunca chega ao
        // atalho do zen: sem este aviso a pessoa ficaria presa no zen com
        // a tela cheia já desfeita.
        setFullscreenElement(null);
        document.dispatchEvent(new Event('fullscreenchange'));

        expect(onExit).toHaveBeenCalledOnce();
    });

    it('says nothing while the screen is still full', () => {
        const onExit = vi.fn();
        renderHook(() => useFullscreen(true, onExit));

        setFullscreenElement(document.documentElement);
        document.dispatchEvent(new Event('fullscreenchange'));

        expect(onExit).not.toHaveBeenCalled();
    });
});
