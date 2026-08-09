import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { ActiveStudyPlan, PlanSubject, PlanSummary } from '@/types/study';
import PlanIndex from './index';

const { post, formPost } = vi.hoisted(() => ({
    post: vi.fn(),
    formPost: vi.fn(),
}));

vi.mock('@inertiajs/react', async () => {
    const { useState } = await import('react');

    return {
        Head: () => null,
        Link: ({ children }: { children: React.ReactNode }) => (
            <a href="#">{children}</a>
        ),
        router: { post },
        useForm: (initial: Record<string, unknown>) => {
            const [data, setState] = useState(initial);

            return {
                data,
                // O `useForm` real aceita chave/valor e o objeto inteiro; o
                // diálogo de edição usa a segunda forma.
                setData: (keyOrData: unknown, value?: unknown) =>
                    setState((current) =>
                        typeof keyOrData === 'string'
                            ? { ...current, [keyOrData]: value }
                            : {
                                  ...current,
                                  ...(keyOrData as Record<string, unknown>),
                              },
                    ),
                post: formPost,
                put: vi.fn(),
                reset: () => setState(initial),
                clearErrors: vi.fn(),
                transform: vi.fn(),
                processing: false,
                errors: {},
            };
        },
    };
});

const activePlan: ActiveStudyPlan = {
    id: 1,
    name: 'Concurso XYZ',
    type: 'contest',
    weeklyGoalMinutes: 1200,
    examDate: null,
    subjects: [],
};

function renderPlan(plans: PlanSummary[]) {
    return render(<PlanIndex plans={plans} activePlan={activePlan} />);
}

describe('PlanIndex', () => {
    beforeEach(() => {
        post.mockReset();
        formPost.mockReset();
    });

    it('offers a way to create another plan when one is already active', async () => {
        renderPlan([{ id: 1, name: 'Concurso XYZ' }]);

        // Sem isto o primeiro plano criado seria o único possível: o painel de
        // criação só aparece no estado vazio.
        await userEvent.click(
            screen.getByRole('button', { name: 'Novo plano' }),
        );

        await userEvent.type(
            screen.getByLabelText('Nome do plano'),
            'Concurso ABC',
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Criar plano' }),
        );

        expect(formPost).toHaveBeenCalledOnce();
        const [url, options] = formPost.mock.calls[0];
        expect(url).toBe('/plano');
        expect(options).toMatchObject({ preserveScroll: true });
    });

    it('reopens the new plan dialog empty after a cancel', async () => {
        renderPlan([{ id: 1, name: 'Concurso XYZ' }]);

        await userEvent.click(
            screen.getByRole('button', { name: 'Novo plano' }),
        );
        await userEvent.type(
            screen.getByLabelText('Nome do plano'),
            'Concurso ABC',
        );
        await userEvent.click(screen.getByRole('button', { name: 'Cancelar' }));

        await userEvent.click(
            screen.getByRole('button', { name: 'Novo plano' }),
        );

        expect(screen.getByLabelText('Nome do plano')).toHaveValue('');
    });

    it('gives each plan form its own field ids', async () => {
        const { unmount } = render(<PlanIndex plans={[]} activePlan={null} />);

        expect(screen.getByLabelText('Nome do plano')).toHaveAttribute(
            'id',
            'plan_name',
        );

        unmount();
        renderPlan([{ id: 1, name: 'Concurso XYZ' }]);

        await userEvent.click(screen.getByRole('button', { name: 'Editar' }));
        expect(screen.getByLabelText('Nome do plano')).toHaveAttribute(
            'id',
            'edit_plan_name',
        );
    });

    it('lets the user switch the active plan once there is more than one', async () => {
        renderPlan([
            { id: 1, name: 'Concurso XYZ' },
            { id: 2, name: 'Concurso ABC' },
        ]);

        await userEvent.click(screen.getByText('Concurso ABC'));

        expect(post).toHaveBeenCalledOnce();
        expect(post.mock.calls[0][0]).toBe('/plano/2/activate');
        expect(post.mock.calls[0][2]).toMatchObject({
            preserveScroll: true,
            showProgress: false,
        });
    });

    it('moves the selection before the server answers', async () => {
        const { container } = renderPlan([
            { id: 1, name: 'Concurso XYZ' },
            { id: 2, name: 'Concurso ABC' },
        ]);

        // `router.post` está mockado e nunca chama `onFinish`: a troca fica
        // eternamente em voo, que é justamente o estado a inspecionar.
        await userEvent.click(screen.getByText('Concurso ABC'));

        expect(screen.getByText('Concurso ABC')).toHaveAttribute(
            'data-state',
            'on',
        );
        // Uma resposta local chega antes disso: anunciar espera aqui só
        // produziria um piscar.
        expect(container.querySelector('[aria-busy="true"]')).toBeNull();
        expect(screen.queryByText('Trocando…')).not.toBeInTheDocument();
    });

    it('announces the wait once it becomes perceptible', async () => {
        const { container } = renderPlan([
            { id: 1, name: 'Concurso XYZ' },
            { id: 2, name: 'Concurso ABC' },
        ]);

        await userEvent.click(screen.getByText('Concurso ABC'));

        await waitFor(() =>
            expect(screen.getByText('Trocando…')).toBeInTheDocument(),
        );
        expect(container.querySelectorAll('[aria-busy="true"]')).toHaveLength(
            2,
        );

        act(() => post.mock.calls[0][2].onFinish());

        expect(container.querySelector('[aria-busy="true"]')).toBeNull();
        expect(screen.queryByText('Trocando…')).not.toBeInTheDocument();
    });

    it('keeps the newest switch pending when an older one settles', async () => {
        renderPlan([
            { id: 1, name: 'Concurso XYZ' },
            { id: 2, name: 'Concurso ABC' },
            { id: 3, name: 'Concurso DEF' },
        ]);

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

    it('announces the exam date in the plan subtitle, in reading format', () => {
        render(
            <PlanIndex
                plans={[]}
                activePlan={{ ...activePlan, examDate: '2027-03-14' }}
            />,
        );

        expect(screen.getByText(/prova em 14\/03\/2027/)).toBeInTheDocument();
    });

    it('says nothing about an exam when the plan has no date', () => {
        render(<PlanIndex plans={[]} activePlan={activePlan} />);

        expect(screen.queryByText(/prova em/)).not.toBeInTheDocument();
    });

    it('names the cycle of each topic only when the track has more than one', () => {
        const { rerender } = render(
            <PlanIndex
                plans={[]}
                activePlan={{ ...activePlan, subjects: [subjectWithCycles(0)] }}
            />,
        );

        expect(screen.queryByText('Ciclo 1')).not.toBeInTheDocument();

        rerender(
            <PlanIndex
                plans={[]}
                activePlan={{ ...activePlan, subjects: [subjectWithCycles(1)] }}
            />,
        );

        expect(screen.getByText('Ciclo 1')).toBeInTheDocument();
        expect(screen.getByText('Ciclo 2')).toBeInTheDocument();
    });
});

/**
 * Uma disciplina com dois tópicos: o segundo no ciclo pedido, o primeiro
 * sempre no ciclo 0.
 */
function subjectWithCycles(secondCycle: number): PlanSubject {
    return {
        id: 10,
        name: 'Álgebra Linear',
        goalMinutes: null,
        topics: [
            {
                id: 100,
                name: 'Matrizes',
                cycle: 0,
                completed: false,
                reviewStatus: null,
            },
            {
                id: 101,
                name: 'Vetores',
                cycle: secondCycle,
                completed: false,
                reviewStatus: null,
            },
        ],
    };
}
