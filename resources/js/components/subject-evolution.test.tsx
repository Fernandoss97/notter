import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { SubjectEvolution, SubjectEvolutionRow } from '@/types/study';
import { SubjectEvolutionTable } from './subject-evolution';

function row(
    overrides: Partial<SubjectEvolutionRow> = {},
): SubjectEvolutionRow {
    return {
        id: 1,
        name: 'Banco de Dados',
        tasksDone: 3,
        tasksTotal: 10,
        progress: 30,
        exercises: 40,
        correct: 30,
        accuracy: 75,
        ...overrides,
    };
}

function evolution(
    overrides: Partial<SubjectEvolution> = {},
): SubjectEvolution {
    return {
        rows: [row()],
        totals: {
            tasksDone: 3,
            tasksTotal: 10,
            progress: 30,
            exercises: 40,
            correct: 30,
            accuracy: 75,
        },
        ...overrides,
    };
}

describe('SubjectEvolutionTable', () => {
    it('reports tasks, questions and accuracy per subject', () => {
        render(<SubjectEvolutionTable evolution={evolution()} />);

        const cells = within(
            screen.getByText('Banco de Dados').closest('tr') as HTMLElement,
        );

        expect(cells.getByText('3/10')).toBeInTheDocument();
        expect(cells.getByText('40')).toBeInTheDocument();
        expect(cells.getByText('30')).toBeInTheDocument();
        expect(cells.getByText('75%')).toBeInTheDocument();
    });

    it('closes with the totals line', () => {
        render(
            <SubjectEvolutionTable
                evolution={evolution({
                    rows: [
                        row({ id: 1, name: 'Banco de Dados' }),
                        row({
                            id: 2,
                            name: 'Português',
                            tasksDone: 1,
                            tasksTotal: 4,
                            exercises: 10,
                            correct: 4,
                            accuracy: 40,
                        }),
                    ],
                    totals: {
                        tasksDone: 4,
                        tasksTotal: 14,
                        progress: 29,
                        exercises: 50,
                        correct: 34,
                        accuracy: 68,
                    },
                })}
            />,
        );

        const totals = within(
            screen.getByText('Totais').closest('tr') as HTMLElement,
        );

        expect(totals.getByText('4/14')).toBeInTheDocument();
        expect(totals.getByText('50')).toBeInTheDocument();
        expect(totals.getByText('68%')).toBeInTheDocument();
    });

    it('marks a weak subject in danger and a strong one in success', () => {
        render(
            <SubjectEvolutionTable
                evolution={evolution({
                    rows: [
                        row({ id: 1, name: 'Banco de Dados', accuracy: 80 }),
                        row({ id: 2, name: 'Português', accuracy: 45 }),
                    ],
                })}
            />,
        );

        expect(screen.getByText('80%')).toHaveClass('text-status-success');
        expect(screen.getByText('45%')).toHaveClass('text-status-danger');
    });

    it('shows a dash instead of zero when nothing was solved', () => {
        render(
            <SubjectEvolutionTable
                evolution={evolution({
                    rows: [row({ exercises: 0, correct: 0, accuracy: null })],
                    totals: {
                        tasksDone: 3,
                        tasksTotal: 10,
                        progress: 30,
                        exercises: 0,
                        correct: 0,
                        accuracy: null,
                    },
                })}
            />,
        );

        // Ausência de questões não é desempenho zero.
        expect(screen.queryByText('0%')).not.toBeInTheDocument();
        expect(screen.getAllByText('—').length).toBeGreaterThan(0);
    });
});
