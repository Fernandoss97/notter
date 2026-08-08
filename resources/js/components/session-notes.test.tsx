import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { SessionNotes } from './session-notes';

const { post } = vi.hoisted(() => ({
    post: vi.fn(() => Promise.resolve()),
}));

// `useHttp` é um hook com estado próprio: o dublê precisa de um useState
// de verdade, senão o textarea nunca re-renderiza e o debounce do
// componente jamais é exercitado.
vi.mock('@inertiajs/react', async () => {
    const { useState } = await import('react');

    return {
        useHttp: (initial: { notes: string }) => {
            const [data, setState] = useState(initial);

            return {
                data,
                setData: (key: string, value: string) =>
                    setState((current) => ({ ...current, [key]: value })),
                post,
                processing: false,
            };
        },
    };
});

describe('SessionNotes', () => {
    beforeEach(() => {
        post.mockClear();
        vi.useFakeTimers({ shouldAdvanceTime: true });
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('does not hit the server on every keystroke', async () => {
        const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
        render(<SessionNotes initialNotes="" />);

        await user.type(screen.getByLabelText('Notas da sessão'), 'rev');

        expect(post).not.toHaveBeenCalled();
    });

    it('saves once the typing settles', async () => {
        const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
        render(<SessionNotes initialNotes="" />);

        await user.type(screen.getByLabelText('Notas da sessão'), 'rev');

        await act(async () => {
            vi.advanceTimersByTime(1000);
        });

        expect(post).toHaveBeenCalledOnce();
        expect(post).toHaveBeenCalledWith('/foco/notes', expect.anything());
    });

    it('stays quiet when the notes never change', async () => {
        render(<SessionNotes initialNotes="já anotado" />);

        await act(async () => {
            vi.advanceTimersByTime(2000);
        });

        expect(post).not.toHaveBeenCalled();
    });
});
