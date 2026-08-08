import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import HistoryIndex from './index';

const { get } = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { get },
}));

function renderHistory(sessions: [] = []) {
    return render(
        <HistoryIndex
            filters={{ period: '7', type: 'all' }}
            total={sessions.length}
            sessions={sessions}
        />,
    );
}

describe('HistoryIndex', () => {
    beforeEach(() => {
        get.mockReset();
    });

    it('moves the filter selection before the server answers', async () => {
        renderHistory();

        const thirtyDays = screen.getByText('30 dias');
        expect(thirtyDays).toHaveAttribute('data-state', 'off');

        await userEvent.click(thirtyDays);

        // `router.get` está mockado e nunca chama `onFinish`: se a seleção
        // dependesse da prop do servidor, ela continuaria em "7 dias".
        expect(thirtyDays).toHaveAttribute('data-state', 'on');
        expect(screen.getByText('7 dias')).toHaveAttribute('data-state', 'off');
    });

    it('requests the merged filters without hijacking the progress bar', async () => {
        renderHistory();

        await userEvent.click(screen.getByText('30 dias'));

        expect(get).toHaveBeenCalledOnce();
        const [, params, options] = get.mock.calls[0];
        expect(params).toEqual({ period: '30', type: 'all' });
        expect(options).toMatchObject({
            preserveState: true,
            preserveScroll: true,
            showProgress: false,
        });
    });

    it('marks the table as busy while the new page is in flight', async () => {
        const { container } = renderHistory();

        expect(container.querySelector('[aria-busy="true"]')).toBeNull();

        await userEvent.click(screen.getByText('30 dias'));

        expect(container.querySelector('[aria-busy="true"]')).not.toBeNull();
    });

    it('shows an empty state instead of a header-only table', () => {
        renderHistory();

        expect(
            screen.getByText('Nenhuma sessão registrada neste período.'),
        ).toBeInTheDocument();
        expect(screen.queryByText('Disciplina')).not.toBeInTheDocument();
    });
});
