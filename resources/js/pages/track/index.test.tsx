import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { TrackCycle, TrackPlan, TrackTask } from '@/types/study';
import TrackIndex from './index';

const { post, formPost, formPut } = vi.hoisted(() => ({
    post: vi.fn(),
    formPost: vi.fn(),
    formPut: vi.fn(),
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
                put: formPut,
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
        exercisesTotal: 0,
        exercisesCorrect: 0,
        accuracy: null,
        ...overrides,
    };
}

function cycle(overrides: Partial<TrackCycle> = {}): TrackCycle {
    return {
        cycle: 0,
        done: 0,
        total: 1,
        exercises: 0,
        correct: 0,
        accuracy: null,
        tasks: [task()],
        ...overrides,
    };
}

describe('TrackIndex', () => {
    beforeEach(() => {
        post.mockReset();
        formPost.mockReset();
        formPut.mockReset();
    });

    it('lists the queue with the task number and its subject', () => {
        render(
            <TrackIndex
                plans={[]}
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
                plans={[]}
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
                plans={[]}
                plan={plan}
                cycles={[
                    cycle({
                        done: 1,
                        tasks: [
                            task({
                                completed: true,
                                completedOn: '14/03/2026',
                            }),
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
        render(<TrackIndex plans={[]} plan={plan} cycles={[cycle()]} />);

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
            screen.getByRole('button', {
                name: 'Desmarcar Aula 00 como concluída',
            }),
        ).toHaveAttribute('aria-pressed', 'true');
    });

    it('undoes a task marked by accident', async () => {
        render(
            <TrackIndex
                plans={[]}
                plan={plan}
                cycles={[
                    cycle({ done: 1, tasks: [task({ completed: true })] }),
                ]}
            />,
        );

        await userEvent.click(
            screen.getByRole('button', {
                name: 'Desmarcar Aula 00 como concluída',
            }),
        );

        expect(post).toHaveBeenCalledWith(
            expect.stringContaining('/uncomplete'),
            {},
            expect.objectContaining({ showProgress: false }),
        );
        // O eco precisa valer nos dois sentidos: desmarcar que continuasse
        // parecendo marcado faria o clique parecer não ter acontecido.
        expect(
            screen.getByRole('button', {
                name: 'Marcar Aula 00 como concluída',
            }),
        ).toHaveAttribute('aria-pressed', 'false');
    });

    it('keeps the empty state mounted but inert once there are tasks', () => {
        const { rerender } = render(
            <TrackIndex plans={[]} plan={plan} cycles={[]} />,
        );

        const empty = document.querySelector('[data-slot="track-empty"]');
        expect(empty).toHaveAttribute('data-visible', 'true');

        rerender(<TrackIndex plans={[]} plan={plan} cycles={[cycle()]} />);

        expect(
            document.querySelector('[data-slot="track-empty"]'),
        ).toHaveAttribute('data-visible', 'false');
    });

    it('defaults a new task to the current cycle, shown 1-based', () => {
        render(
            <TrackIndex
                plans={[]}
                plan={plan}
                cycles={[cycle({ cycle: 2 })]}
            />,
        );

        expect(screen.getByLabelText('Ciclo')).toHaveValue(3);
    });

    it('echoes a task being added while the server confirms', async () => {
        render(<TrackIndex plans={[]} plan={plan} cycles={[cycle()]} />);

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

    it('reports the questions of a task and of its cycle', () => {
        render(
            <TrackIndex
                plans={[]}
                plan={plan}
                cycles={[
                    cycle({
                        exercises: 20,
                        correct: 15,
                        accuracy: 75,
                        tasks: [
                            task({
                                exercisesTotal: 20,
                                exercisesCorrect: 15,
                                accuracy: 75,
                            }),
                        ],
                    }),
                ]}
            />,
        );

        expect(
            document.querySelector('[data-slot="track-task"]'),
        ).toHaveTextContent('15/20 questões · 75%');
        expect(
            screen.getByText(/15\/20 questões · 75% de acerto/),
        ).toBeInTheDocument();
    });

    it('says nothing about questions when none were recorded', () => {
        render(<TrackIndex plans={[]} plan={plan} cycles={[cycle()]} />);

        expect(screen.queryByText(/questões/)).not.toBeInTheDocument();
    });

    it('records the questions of a task, replacing the pair', async () => {
        render(
            <TrackIndex
                plans={[]}
                plan={plan}
                cycles={[
                    cycle({
                        tasks: [
                            task({
                                exercisesTotal: 10,
                                exercisesCorrect: 8,
                                accuracy: 80,
                            }),
                        ],
                    }),
                ]}
            />,
        );

        await userEvent.click(
            screen.getByRole('button', {
                name: 'Registrar questões de Aula 00',
            }),
        );

        // O diálogo abre com o que já está registrado: é correção, não
        // um novo lançamento em branco.
        expect(screen.getByLabelText('Questões feitas')).toHaveValue(10);
        expect(screen.getByLabelText('Acertos')).toHaveValue(8);

        await userEvent.clear(screen.getByLabelText('Acertos'));
        await userEvent.type(screen.getByLabelText('Acertos'), '9');
        await userEvent.click(screen.getByRole('button', { name: 'Salvar' }));

        expect(formPut).toHaveBeenCalledWith(
            expect.stringContaining('/questoes'),
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('imports a paste from the spreadsheet', async () => {
        render(<TrackIndex plans={[]} plan={plan} cycles={[cycle()]} />);

        await userEvent.click(
            screen.getByRole('button', { name: 'Importar trilha' }),
        );

        await userEvent.type(
            screen.getByLabelText('Tarefas'),
            'Banco de Dados\tEstudo da Aula 00',
        );
        await userEvent.click(screen.getByRole('button', { name: 'Importar' }));

        expect(formPost).toHaveBeenCalledWith(
            expect.stringContaining('/trilha/importar'),
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('offers no import when there is no plan to import into', () => {
        render(<TrackIndex plans={[]} plan={null} cycles={[]} />);

        expect(
            screen.queryByRole('button', { name: 'Importar trilha' }),
        ).not.toBeInTheDocument();
    });

    it('lets the user switch plans from the track', () => {
        render(
            <TrackIndex
                plans={[
                    { id: 1, name: 'Concurso XYZ' },
                    { id: 2, name: 'Concurso ABC' },
                ]}
                plan={plan}
                cycles={[cycle()]}
            />,
        );

        expect(screen.getByText('Plano ativo')).toBeInTheDocument();
        expect(screen.getByText('Concurso ABC')).toBeInTheDocument();
    });

    it('points to the plan screen when there is no active plan', () => {
        render(<TrackIndex plans={[]} plan={null} cycles={[]} />);

        expect(
            screen.getByRole('link', { name: 'Criar plano de estudos' }),
        ).toBeInTheDocument();
    });
});
