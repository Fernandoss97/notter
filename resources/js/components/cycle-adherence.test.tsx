import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { CycleAdherence, CycleAdherenceRow } from '@/types/study';
import { CycleAdherencePanel } from './cycle-adherence';

function row(overrides: Partial<CycleAdherenceRow> = {}): CycleAdherenceRow {
    return {
        id: 1,
        name: 'Direito',
        targetPercent: 50,
        actualPercent: 50,
        minutes: 120,
        drift: 0,
        ...overrides,
    };
}

function adherence(overrides: Partial<CycleAdherence> = {}): CycleAdherence {
    return {
        closedLaps: 3,
        blocksDone: 7,
        rows: [row()],
        ...overrides,
    };
}

describe('CycleAdherencePanel', () => {
    it('counts the laps and blocks behind', () => {
        render(<CycleAdherencePanel cycle={adherence()} />);

        expect(screen.getByText('3')).toBeInTheDocument();
        expect(screen.getByText('voltas fechadas')).toBeInTheDocument();
        expect(screen.getByText('blocos concluídos')).toBeInTheDocument();
    });

    it('says a single lap in the singular', () => {
        render(
            <CycleAdherencePanel
                cycle={adherence({ closedLaps: 1, blocksDone: 1 })}
            />,
        );

        expect(screen.getByText('volta fechada')).toBeInTheDocument();
        expect(screen.getByText('bloco concluído')).toBeInTheDocument();
    });

    it('puts the real share next to the one the cycle asked for', () => {
        render(
            <CycleAdherencePanel
                cycle={adherence({
                    rows: [
                        row({ actualPercent: 20, targetPercent: 50, minutes: 40 }),
                    ],
                })}
            />,
        );

        expect(screen.getByText(/20% de 50%/)).toBeInTheDocument();
        expect(screen.getByText(/40min/)).toBeInTheDocument();
    });

    it('names the subject being avoided', () => {
        render(
            <CycleAdherencePanel
                cycle={adherence({
                    rows: [
                        row({ id: 1, name: 'Direito', drift: 30 }),
                        row({ id: 2, name: 'Português', drift: -30 }),
                    ],
                })}
            />,
        );

        expect(
            screen.getByText(
                /Português ficou com menos tempo do que o ciclo pede/,
            ),
        ).toBeInTheDocument();
    });

    it('lists several avoided subjects in one sentence', () => {
        render(
            <CycleAdherencePanel
                cycle={adherence({
                    rows: [
                        row({ id: 1, name: 'Português', drift: -20 }),
                        row({ id: 2, name: 'RLM', drift: -30 }),
                    ],
                })}
            />,
        );

        expect(
            screen.getByText(/RLM e Português ficaram com menos tempo/),
        ).toBeInTheDocument();
    });

    it('says nothing is off when the drift is within the normal swing', () => {
        render(
            <CycleAdherencePanel
                cycle={adherence({ rows: [row({ drift: -3 })] })}
            />,
        );

        expect(
            screen.getByText(/seguiu o que o ciclo pediu/),
        ).toBeInTheDocument();
    });

    it('describes each bar for assistive tech', () => {
        render(
            <CycleAdherencePanel
                cycle={adherence({
                    rows: [
                        row({
                            name: 'Direito',
                            actualPercent: 20,
                            targetPercent: 50,
                        }),
                    ],
                })}
            />,
        );

        expect(
            screen.getByRole('progressbar', {
                name: 'Direito: 20% do tempo, alvo 50%',
            }),
        ).toHaveAttribute('aria-valuenow', '20');
    });
});
