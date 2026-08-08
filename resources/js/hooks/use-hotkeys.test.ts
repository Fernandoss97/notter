import { renderHook } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { useHotkeys } from './use-hotkeys';

describe('useHotkeys', () => {
    it('runs the handler for a bare key', async () => {
        const toggle = vi.fn();
        renderHook(() => useHotkeys({ ' ': toggle }));

        await userEvent.keyboard(' ');

        expect(toggle).toHaveBeenCalledOnce();
    });

    it('matches keys case-insensitively', async () => {
        const zen = vi.fn();
        renderHook(() => useHotkeys({ f: zen }));

        await userEvent.keyboard('F');

        expect(zen).toHaveBeenCalledOnce();
    });

    it('stays out of the way while the user types in a field', async () => {
        const complete = vi.fn();
        renderHook(() => useHotkeys({ e: complete }));

        const input = document.createElement('input');
        document.body.append(input);
        input.focus();

        await userEvent.keyboard('e');

        expect(complete).not.toHaveBeenCalled();
        expect(input).toHaveValue('e');

        input.remove();
    });

    it('ignores shortcuts with a modifier so browser commands keep working', async () => {
        const complete = vi.fn();
        renderHook(() => useHotkeys({ e: complete }));

        await userEvent.keyboard('{Control>}e{/Control}');

        expect(complete).not.toHaveBeenCalled();
    });

    it('does nothing when disabled', async () => {
        const zen = vi.fn();
        renderHook(() => useHotkeys({ f: zen }, false));

        await userEvent.keyboard('f');

        expect(zen).not.toHaveBeenCalled();
    });

    it('stops listening once unmounted', async () => {
        const zen = vi.fn();
        const { unmount } = renderHook(() => useHotkeys({ f: zen }));

        unmount();
        await userEvent.keyboard('f');

        expect(zen).not.toHaveBeenCalled();
    });
});
