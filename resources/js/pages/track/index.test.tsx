import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { TrackCycle, TrackPlan, TrackTask } from '@/types/study';
import TrackIndex from './index';

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
                setData: (keyOrData: unknown, value?: unknown) =>
                    setState((current) =>
                        typeof keyOrData === 'string'
                            ? { ...current, [keyOrData]: value }
                            : typeof keyOrData === 'function'
                              ? (
                                    keyOrData as (
                                        c: Record<string, unknown>,
                                    ) => Record<string, unknown>
                                )(current)
                              : {
                                    ...current,
                                    ...(keyOrData as Record<string, unknown>),
                                },
                    ),
                post: formPost,
                reset: () => setState(initial),
                clearErrors: vi.fn(),
                transform: vi.fn(),
                processing: false,
                errors: {},
            };
        },
    };
});

const plan: TrackPlan = {
    id: 1,
    name: 'Concurso XYZ',
    subjects: [
        { id: 10, name: 'Banco de Dados' },
        { id: 11, name: 'Raciocínio Lógico' },
    ],
};

function task(overrides: Partial<TrackTask> = {}): TrackTask {
    return {
        id: 100,
        number: 1,
        name: 'Aula 00',
        subjectName: 'Banco de Dados',
        completed: false,
        completedOn: null,
        reviewStatus: null,
        ...overrides,
    };
}

function cycle(overrides: Partial<TrackCycle> = {}): TrackCycle {
    return {
        cycle: 0,
        done: 0,
        total: 1,
        tasks: [task()],
        ...overrides,
    };
}

describe('TrackIndex', () => {
    beforeEach(() => {
        post.mockReset();
        formPost.mockReset();
    });

    it('lists the queue with the task number and its subject', () => {
        render(
            <TrackIndex
                plan={plan}
                cycles={[
                    cycle({
                        total: 2,
                        tasks: [
                            task({ id: 100, number: 1 }),
                            task({
                                id: 101,
                                number: 2,
                                name: 'Estruturas Lógicas I',
                                subjectName: 'Raciocínio Lógico',
                            }),
                        ],
                    }),
                ]}
            />,
        );

        const rows = document.querySelectorAll('[data-slot="track-task"]');

        expect(rows).toHaveLength(2);
        expect(rows[0]).toHaveTextContent('1');
        expect(rows[0]).toHaveTextContent('Aula 00');
        expect(rows[0]).toHaveTextContent('Banco de Dados');
        expect(rows[1]).toHaveTextContent('Estruturas Lógicas I');
    });

    it('shows the cycle 1-based and its progress', () => {
        render(
            <TrackIndex
                plan={plan}
                cycles={[cycle({ cycle: 0, done: 1, total: 2 })]}
            />,
        );

        expect(screen.getByText('Ciclo 1')).toBeInTheDocument();
        expect(screen.getByText('1 de 2 tarefas')).toBeInTheDocument();
    });

    it('reports when a task was completed', () => {
        render(
            <TrackIndex
                plan={plan}
                cycles={[
                    cycle({
                        done: 1,
                        tasks: [
                            task({ completed: true, completedOn: '14/03/2026' }),
                        ],
                    }),
                ]}
            />,
        );

        expect(
            screen.getByText(/concluída em 14\/03\/2026/),
        ).toBeInTheDocument();
    });

    it('marks a task as done optimistically, without a progress bar', async () => {
        render(<TrackIndex plan={plan} cycles={[cycle()]} />);

        await userEvent.click(
            screen.getByRole('button', {
                name: 'Marcar Aula 00 como concluída',
            }),
        );

        expect(post).toHaveBeenCalledWith(
            expect.stringContaining('/complete'),
            {},
            expect.objectContaining({ showProgress: false }),
        );
        // O eco: o botão já responde como concluído antes da resposta chegar.
        expect(
            screen.getByRole('button', { name: 'Aula 00 concluída' }),
        ).toBeDisabled();
    });

    it('keeps the empty state mounted but inert once there are tasks', () => {
        const { rerender } = render(<TrackIndex plan={plan} cycles={[]} />);

        const empty = document.querySelector('[data-slot="track-empty"]');
        expect(empty).toHaveAttribute('data-visible', 'true');

        rerender(<TrackIndex plan={plan} cycles={[cycle()]} />);

        expect(
            document.querySelector('[data-slot="track-empty"]'),
        ).toHaveAttribute('data-visible', 'false');
    });

    it('defaults a new task to the current cycle, shown 1-based', () => {
        render(<TrackIndex plan={plan} cycles={[cycle({ cycle: 2 })]} />);

        expect(screen.getByLabelText('Ciclo')).toHaveValue(3);
    });

    it('echoes a task being added while the server confirms', async () => {
        render(<TrackIndex plan={plan} cycles={[cycle()]} />);

        await userEvent.type(
            screen.getByLabelText('Tarefa'),
            'Estudo da Aula 05',
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Adicionar' }),
        );

        expect(formPost).toHaveBeenCalledWith(
            expect.stringContaining('/plano/topics'),
            expect.objectContaining({ showProgress: false }),
        );
        expect(screen.getByText('Estudo da Aula 05')).toBeInTheDocument();
        expect(screen.getByText('Adicionando…')).toBeInTheDocument();
    });

    it('points to the plan screen when there is no active plan', () => {
        render(<TrackIndex plan={null} cycles={[]} />);

        expect(
            screen.getByRole('link', { name: 'Criar plano de estudos' }),
        ).toBeInTheDocument();
    });
});
