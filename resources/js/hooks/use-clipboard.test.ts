import { act, renderHook, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { useClipboard } from './use-clipboard';

describe('useClipboard', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('copies text and exposes it as the copied value', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.assign(navigator, { clipboard: { writeText } });

        const { result } = renderHook(() => useClipboard());
        const [, copy] = result.current;

        await act(async () => {
            await copy('hello world');
        });

        expect(writeText).toHaveBeenCalledWith('hello world');
        await waitFor(() => expect(result.current[0]).toBe('hello world'));
    });

    it('returns false when the clipboard API is unavailable', async () => {
        Object.assign(navigator, { clipboard: undefined });

        const { result } = renderHook(() => useClipboard());
        const [, copy] = result.current;

        await expect(copy('nope')).resolves.toBe(false);
        expect(result.current[0]).toBeNull();
    });
});
