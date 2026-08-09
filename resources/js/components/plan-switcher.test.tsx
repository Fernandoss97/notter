import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { PlanSummary } from '@/types/study';
import { PlanSwitcher } from './plan-switcher';

const { post } = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock('@inertiajs/react', () => ({ router: { post } }));

const plans: PlanSummary[] = [
    { id: 1, name: 'Concurso XYZ' },
    { id: 2, name: 'Concurso ABC' },
];

describe('PlanSwitcher', () => {
    beforeEach(() => {
        post.mockReset();
    });

    it('stays out of the way when there is only one plan', () => {
        render(<PlanSwitcher plans={[plans[0]]} activePlanId={1} />);

        expect(screen.queryByText('Plano ativo')).not.toBeInTheDocument();
    });

    it('activates the chosen plan and moves the selection before the answer', async () => {
        render(<PlanSwitcher plans={plans} activePlanId={1} />);

        await userEvent.click(screen.getByText('Concurso ABC'));

        expect(post).toHaveBeenCalledWith(
            expect.stringContaining('/activate'),
            {},
            expect.objectContaining({ showProgress: false }),
        );
        expect(screen.getByText('Concurso ABC')).toHaveAttribute(
            'data-state',
            'on',
        );
    });

    it('tells the page to wait, and to stop waiting when the answer lands', async () => {
        const onBusyChange = vi.fn();

        render(
            <PlanSwitcher
                plans={plans}
                activePlanId={1}
                onBusyChange={onBusyChange}
            />,
        );

        await userEvent.click(screen.getByText('Concurso ABC'));

        // A espera só se anuncia depois do atraso: um piscar de 100ms seria
        // pior que não mostrar nada.
        expect(onBusyChange).not.toHaveBeenCalled();

        await waitFor(() =>
            expect(
                screen.getByRole('status', { name: 'Trocando de plano' }),
            ).toBeInTheDocument(),
        );
        expect(onBusyChange).toHaveBeenCalledWith(true);

        act(() => post.mock.calls[0][2].onFinish());

        expect(onBusyChange).toHaveBeenLastCalledWith(false);
        expect(
            screen.queryByRole('status', { name: 'Trocando de plano' }),
        ).not.toBeInTheDocument();
    });

    it('keeps the newest switch pending when an older one settles', async () => {
        render(
            <PlanSwitcher
                plans={[...plans, { id: 3, name: 'Concurso DEF' }]}
                activePlanId={1}
            />,
        );

        await userEvent.click(screen.getByText('Concurso ABC'));
        await userEvent.click(screen.getByText('Concurso DEF'));

        // O Inertia cancela a visita anterior; o `onFinish` dela chega depois
        // e não pode devolver a seleção ao plano do servidor.
        act(() => post.mock.calls[0][2].onFinish());

        expect(screen.getByText('Concurso DEF')).toHaveAttribute(
            'data-state',
            'on',
        );
    });
});
