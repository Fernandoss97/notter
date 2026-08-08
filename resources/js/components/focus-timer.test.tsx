import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { FocusTimer } from './focus-timer';
import type { FocusTimerProps } from './focus-timer';

function renderTimer(overrides: Partial<FocusTimerProps> = {}) {
    const props: FocusTimerProps = {
        phase: 'focus',
        paused: false,
        elapsedSeconds: 600,
        plannedSeconds: 3000,
        completedCycles: 0,
        cyclesBeforeLongBreak: 4,
        subjectLabel: 'Álgebra Linear',
        topicLabel: 'Autovalores',
        onToggle: vi.fn(),
        onAdvance: vi.fn(),
        onSkipBreak: vi.fn(),
        ...overrides,
    };

    render(<FocusTimer {...props} />);

    return props;
}

describe('FocusTimer', () => {
    it('counts down instead of up', () => {
        renderTimer({ elapsedSeconds: 600, plannedSeconds: 3000 });

        expect(screen.getByText('40:00')).toBeInTheDocument();
        expect(screen.getByText(/10:00 de 50 min/)).toBeInTheDocument();
    });

    it('never shows negative time when the phase overruns', () => {
        renderTimer({ elapsedSeconds: 3200, plannedSeconds: 3000 });

        expect(screen.getByText('00:00')).toBeInTheDocument();
    });

    it('announces the phase without reading the clock every second', () => {
        renderTimer();

        const label = screen.getByText('Em foco');
        expect(label).toHaveAttribute('aria-live', 'polite');
        expect(screen.getByRole('timer')).not.toHaveAttribute('aria-live');
    });

    it('exposes the remaining time as the timer label', () => {
        renderTimer({ elapsedSeconds: 600, plannedSeconds: 3000 });

        expect(
            screen.getByRole('timer', { name: 'Tempo restante: 40:00' }),
        ).toBeInTheDocument();
    });

    it('calls a break a break, not focus', () => {
        renderTimer({ phase: 'short_break' });

        expect(screen.getByText('Intervalo')).toBeInTheDocument();
    });

    it('says the cycle is done and offers the next phase when time is up', async () => {
        const props = renderTimer({ elapsedSeconds: 3000 });

        expect(screen.getByText('Ciclo concluído')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Pausar' }),
        ).not.toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'Começar intervalo' }),
        );

        expect(props.onAdvance).toHaveBeenCalledOnce();
    });

    it('offers to skip a break that is still running', async () => {
        const props = renderTimer({ phase: 'short_break', elapsedSeconds: 60 });

        await userEvent.click(
            screen.getByRole('button', { name: 'Pular intervalo' }),
        );

        expect(props.onSkipBreak).toHaveBeenCalledOnce();
    });

    it('never offers to skip a focus phase', () => {
        renderTimer({ phase: 'focus', elapsedSeconds: 60 });

        expect(
            screen.queryByRole('button', { name: 'Pular intervalo' }),
        ).not.toBeInTheDocument();
    });

    it('toggles between pausing and resuming', async () => {
        const props = renderTimer({ paused: true });

        expect(screen.getByText('Pausado')).toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Retomar' }));

        expect(props.onToggle).toHaveBeenCalledOnce();
    });

    it('shows the subject and topic together', () => {
        renderTimer();

        expect(
            screen.getByText('Álgebra Linear · Autovalores'),
        ).toBeInTheDocument();
    });
});
