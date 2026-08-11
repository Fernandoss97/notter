import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import type { PomodoroSettingFull } from '@/types/study';
import PomodoroEdit from './pomodoro';

const { put } = vi.hoisted(() => ({ put: vi.fn() }));

vi.mock('@inertiajs/react', async () => {
    const { useState } = await import('react');

    return {
        Head: () => null,
        useForm: (initial: Record<string, unknown>) => {
            const [data, setState] = useState(initial);

            return {
                data,
                setData: (key: string, value: unknown) =>
                    setState((current) => ({ ...current, [key]: value })),
                put,
                processing: false,
                errors: {},
            };
        },
    };
});

const settings: PomodoroSettingFull = {
    focusMinutes: 50,
    shortBreakMinutes: 10,
    longBreakMinutes: 25,
    cyclesBeforeLongBreak: 4,
    dailyGoalMinutes: 120,
    autoStartBreak: true,
    autoStartFocus: false,
    logSessionOnFinish: true,
    silentModeDuringFocus: true,
    excludeBreaksFromStudyTime: true,
};

describe('pomodoro settings screen', () => {
    it('carries the daily goal the user already chose', () => {
        render(<PomodoroEdit settings={settings} />);

        expect(screen.getByLabelText('Minutos por dia')).toHaveValue(120);
    });

    // A meta semanal continua sendo do plano, e as duas são independentes:
    // sem o equivalente à vista, elas discordariam sem ninguém notar.
    it('shows what the daily goal adds up to in a week', async () => {
        const user = userEvent.setup();
        render(<PomodoroEdit settings={settings} />);

        expect(screen.getByText('14h 0min')).toBeInTheDocument();

        const input = screen.getByLabelText('Minutos por dia');
        await user.clear(input);
        await user.type(input, '90');

        expect(screen.getByText('10h 30min')).toBeInTheDocument();
    });

    it('keeps the goal inside what a day holds', () => {
        render(<PomodoroEdit settings={settings} />);

        const input = screen.getByLabelText('Minutos por dia');
        expect(input).toHaveAttribute('min', '5');
        expect(input).toHaveAttribute('max', '960');
    });
});
