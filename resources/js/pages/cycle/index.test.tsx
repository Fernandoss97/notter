import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { CycleSlotSummary, StudyCycle } from '@/types/study';
import CycleIndex from './index';

const { post, put, destroy, formPost } = vi.hoisted(() => ({
    post: vi.fn(),
    put: vi.fn(),
    destroy: vi.fn(),
    formPost: vi.fn(),
}));

vi.mock('@inertiajs/react', async () => {
    const { useState } = await import('react');

    return {
        Head: () => null,
        Link: ({ children }: { children: React.ReactNode }) => (
            <a href="#">{children}</a>
        ),
        router: { post, put, delete: destroy },
        useForm: (initial: Record<string, unknown>) => {
            const [data, setState] = useState(initial);

            return {
                data,
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
                reset: () => setState(initial),
                clearErrors: vi.fn(),
                transform: vi.fn(),
                processing: false,
                errors: {},
            };
        },
    };
});

vi.mock('@/components/plan-switcher', () => ({ PlanSwitcher: () => null }));

const plan = { id: 1, name: 'Concurso XYZ' };

function slot(overrides: Partial<CycleSlotSummary> = {}): CycleSlotSummary {
    const base: CycleSlotSummary = {
        id: 1,
        subjectId: 10,
        subjectName: 'Direito Constitucional',
        subjectMode: 'reading',
        blockMinutes: 60,
        boosted: false,
        state: 'active',
        isCurrent: false,
        block: null,
    };

    return { ...base, ...overrides };
}

function block(overrides: Partial<NonNullable<CycleSlotSummary['block']>> = {}) {
    return {
        id: 100,
        subjectId: 10,
        subjectName: 'Direito Constitucional',
        lap: 1,
        targetMinutes: 60,
        elapsedSeconds: 0,
        remainingMinutes: 60,
        progressPercent: 0,
        completed: false,
        isActive: false,
        ...overrides,
    };
}

const cycle: StudyCycle = {
    lap: 2,
    currentBlockId: 100,
    readingCount: 2,
    recommendedReading: 6,
    slots: [
        slot({
            id: 1,
            isCurrent: true,
            block: block({ elapsedSeconds: 30 * 60, progressPercent: 50 }),
        }),
        slot({
            id: 2,
            subjectId: 11,
            subjectName: 'Português',
            blockMinutes: 90,
            block: block({ id: 101, subjectId: 11, targetMinutes: 90 }),
        }),
    ],
};

function renderCycle(overrides: Partial<StudyCycle> = {}) {
    return render(
        <CycleIndex
            plans={[{ id: 1, name: 'Concurso XYZ' }]}
            plan={plan}
            cycle={{ ...cycle, ...overrides }}
            availableSubjects={[{ id: 12, name: 'Informática' }]}
        />,
    );
}

beforeEach(() => {
    vi.clearAllMocks();
});

describe('cycle screen', () => {
    it('shows the lap and how much of it is done', () => {
        renderCycle();

        expect(screen.getByText(/volta 2/)).toBeInTheDocument();
        expect(screen.getByText(/0 de 2 blocos/)).toBeInTheDocument();
    });

    it('marks whose turn it is', () => {
        renderCycle();

        expect(screen.getByText('É a vez')).toBeInTheDocument();
    });

    it('shows how far the current block has gone', () => {
        renderCycle();

        expect(screen.getByText(/30min de 1h 0min/)).toBeInTheDocument();
    });

    it('marks a finished block as done and offers no way to close it again', () => {
        renderCycle({
            slots: [
                slot({
                    id: 1,
                    isCurrent: true,
                    block: block({ completed: true, progressPercent: 100 }),
                }),
            ],
        });

        expect(screen.getByText('Concluído')).toBeInTheDocument();
        expect(screen.queryByText('Encerrar')).not.toBeInTheDocument();
    });

    it('lets the current block be called done ahead of the target', async () => {
        const user = userEvent.setup();
        renderCycle();

        await user.click(screen.getByText('Encerrar'));

        expect(post).toHaveBeenCalledWith(
            expect.stringContaining('/ciclo/blocos/100/complete'),
            {},
            expect.anything(),
        );
    });

    it('keeps a seat out of the rotation visible so it can come back', () => {
        renderCycle({
            slots: [slot({ id: 1, state: 'paused', block: null })],
        });

        expect(screen.getByText('Fora do rodízio')).toBeInTheDocument();
        expect(
            screen.getByLabelText(
                'Devolver Direito Constitucional ao rodízio',
            ),
        ).toBeInTheDocument();
    });

    it('sends the new order when a seat moves down', async () => {
        const user = userEvent.setup();
        renderCycle();

        await user.click(
            screen.getByLabelText(
                'Mover Direito Constitucional para baixo',
            ),
        );

        expect(post).toHaveBeenCalledWith(
            expect.stringContaining('/ciclo/cadeiras/ordem'),
            { slots: [2, 1] },
            expect.anything(),
        );
    });

    it('cannot move the first seat up nor the last one down', () => {
        renderCycle();

        expect(
            screen.getByLabelText('Mover Direito Constitucional para cima'),
        ).toBeDisabled();
        expect(
            screen.getByLabelText('Mover Português para baixo'),
        ).toBeDisabled();
    });

    it('warns about blocks longer than the recommended 90 minutes', async () => {
        const user = userEvent.setup();
        renderCycle();

        const warning = screen.getByText(/Blocos acima de 90 minutos/);
        expect(warning).toHaveAttribute('aria-hidden', 'true');

        const input = screen.getByLabelText('Minutos por bloco');
        await user.clear(input);
        await user.type(input, '120');

        expect(screen.getByText(/Blocos acima de 90 minutos/)).toHaveAttribute(
            'aria-hidden',
            'false',
        );
    });

    it('shows the empty state only while the rotation has no seats', () => {
        const { rerender } = renderCycle({ slots: [] });

        const empty = screen.getByText(/Nenhuma disciplina no rodízio ainda/);
        expect(empty).toHaveAttribute('data-visible', 'true');

        rerender(
            <CycleIndex
                plans={[{ id: 1, name: 'Concurso XYZ' }]}
                plan={plan}
                cycle={cycle}
                availableSubjects={[]}
            />,
        );

        expect(
            screen.getByText(/Nenhuma disciplina no rodízio ainda/),
        ).toHaveAttribute('data-visible', 'false');
    });

    it('warns when too many subjects are in first contact at once', () => {
        const { rerender } = renderCycle({ readingCount: 7 });

        expect(
            screen.getByText(/7 disciplinas em leitura ao mesmo tempo/),
        ).toHaveAttribute('data-visible', 'true');

        rerender(
            <CycleIndex
                plans={[{ id: 1, name: 'Concurso XYZ' }]}
                plan={plan}
                cycle={{ ...cycle, readingCount: 6 }}
                availableSubjects={[]}
            />,
        );

        expect(
            screen.getByText(/disciplinas em leitura ao mesmo tempo/),
        ).toHaveAttribute('data-visible', 'false');
    });

    // Abrir um Select do Radix não funciona no jsdom (ele usa
    // `hasPointerCapture`), então aqui se verifica o que a linha mostra;
    // a troca em si é exercitada no teste de browser.
    it('shows where each subject stands', () => {
        renderCycle({
            slots: [
                slot({ id: 1, subjectMode: 'reading' }),
                slot({
                    id: 2,
                    subjectName: 'Português',
                    subjectMode: 'review',
                }),
            ],
        });

        expect(
            screen.getByLabelText('Situação de Direito Constitucional'),
        ).toHaveTextContent('Leitura');
        expect(screen.getByLabelText('Situação de Português')).toHaveTextContent(
            'Revisão',
        );
    });

    it('marks a subject in its initial immersion', () => {
        renderCycle({
            slots: [slot({ id: 1, boosted: true, isCurrent: true })],
        });

        expect(screen.getByText('Imersão')).toBeInTheDocument();
    });

    it('offers no trimming while the rotation is already small', () => {
        renderCycle({ slots: [slot({ id: 1 }), slot({ id: 2 })] });

        expect(screen.queryByText('Enxugar ciclo')).not.toBeInTheDocument();
    });

    it('trims the rotation down to the first seats', async () => {
        const user = userEvent.setup();
        renderCycle({
            slots: [slot({ id: 1 }), slot({ id: 2 }), slot({ id: 3 })],
        });

        await user.click(screen.getByText('Enxugar ciclo'));
        await user.clear(screen.getByLabelText('Quantas disciplinas manter'));
        await user.type(
            screen.getByLabelText('Quantas disciplinas manter'),
            '2',
        );
        await user.click(screen.getByRole('button', { name: 'Enxugar' }));

        expect(post).toHaveBeenCalledWith(
            expect.stringContaining('/ciclo/enxugar'),
            { keep: 2 },
            expect.anything(),
        );
    });

    it('falls back to an invitation when there is no plan', () => {
        render(
            <CycleIndex
                plans={[]}
                plan={null}
                cycle={null}
                availableSubjects={[]}
            />,
        );

        expect(screen.getByText('Criar plano de estudos')).toBeInTheDocument();
    });
});
