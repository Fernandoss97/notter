import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { hiddenTimePreference } from '@/hooks/use-hidden-time';
import type {
    DueReview,
    FocusSession,
    PomodoroSettingSummary,
    StudyPlanSummary,
} from '@/types/study';
import FocusShow from './show';

const { post, announcePhaseEnd } = vi.hoisted(() => ({
    post: vi.fn(),
    announcePhaseEnd: vi.fn(),
}));

vi.mock('@inertiajs/react', async () => {
    const { useState } = await import('react');

    return {
        Head: () => null,
        Link: ({ children }: { children: React.ReactNode }) => (
            <a href="#">{children}</a>
        ),
        router: { post },
        usePage: () => ({ props: {} }),
        useForm: (initial: Record<string, unknown>) => {
            const [data, setState] = useState(initial);

            return {
                data,
                setData: (key: string, value: unknown) =>
                    setState((current) => ({ ...current, [key]: value })),
                post: vi.fn(),
                transform: vi.fn(),
                processing: false,
                errors: {},
            };
        },
        useHttp: (initial: Record<string, unknown>) => ({
            data: initial,
            setData: vi.fn(),
            post: vi.fn(),
            processing: false,
        }),
    };
});

vi.mock('@/lib/focus-alerts', () => ({
    announcePhaseEnd,
    prepareAlerts: vi.fn(),
    requestNotificationPermission: vi.fn(),
    notificationsBlocked: vi.fn(() => false),
    unlockAudio: vi.fn(),
}));

const plan: StudyPlanSummary = {
    id: 1,
    name: 'Concurso XYZ',
    subjects: [
        {
            id: 10,
            name: 'Álgebra Linear',
            topics: [
                {
                    id: 100,
                    name: 'Autovalores',
                    cycle: 0,
                    position: 0,
                    completed: false,
                    reviewStatus: null,
                },
            ],
        },
    ],
};

const pomodoroSetting: PomodoroSettingSummary = {
    focusMinutes: 50,
    shortBreakMinutes: 10,
    longBreakMinutes: 25,
    cyclesBeforeLongBreak: 4,
    autoStartBreak: true,
    autoStartFocus: false,
    logSessionOnFinish: true,
    silentModeDuringFocus: true,
};

function session(overrides: Partial<FocusSession> = {}): FocusSession {
    return {
        id: 1,
        subjectId: 10,
        topicId: 100,
        activityType: null,
        state: 'running',
        phase: 'focus',
        elapsedSeconds: 0,
        plannedSeconds: 3000,
        focusSeconds: 0,
        completedCycles: 0,
        notes: '',
        ...overrides,
    };
}

function renderFocus(
    active: FocusSession | null,
    dueReviews: DueReview[] = [],
    settings: PomodoroSettingSummary = pomodoroSetting,
) {
    return render(
        <FocusShow
            plans={[]}
            plan={plan}
            session={active}
            pomodoroSetting={settings}
            todayPomodoros={2}
            today={{ minutes: 40, goalMinutes: 100 }}
            dueReviews={dueReviews}
        />,
    );
}

describe('FocusShow', () => {
    beforeEach(() => {
        post.mockReset();
        announcePhaseEnd.mockReset();
        // Preferência de módulo: sem zerar, a escolha de um teste
        // atravessaria para o próximo.
        hiddenTimePreference.set(false);
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('asks what to study when there is no session', () => {
        renderFocus(null);

        expect(screen.getByText('Pronto para focar')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /Iniciar sessão de foco/ }),
        ).toBeInTheDocument();
    });

    it('shows the day progress against the goal', () => {
        renderFocus(null);

        expect(screen.getByText('40min')).toBeInTheDocument();
        expect(screen.getByText('meta 1h 40min')).toBeInTheDocument();
        expect(screen.getByText('2 pomodoros hoje')).toBeInTheDocument();
    });

    it('leaves closing the cycle to the session bar', () => {
        vi.useFakeTimers();
        renderFocus(session({ elapsedSeconds: 2999 }));

        act(() => {
            vi.advanceTimersByTime(5000);
        });

        // A barra está montada em todas as telas, inclusive nesta, e é
        // ela que pede o avanço (ver use-phase-end). Fazer o mesmo aqui
        // fecharia o ciclo duas vezes.
        expect(
            post.mock.calls.filter(([url]) => url === '/foco/advance'),
        ).toHaveLength(0);
        expect(announcePhaseEnd).not.toHaveBeenCalled();
    });

    it('keeps the review queue reachable but muted while focusing', () => {
        renderFocus(session(), [
            {
                id: 1,
                subjectId: 10,
                subjectName: 'Álgebra Linear',
                topicId: 100,
                topicName: 'Autovalores',
                sequence: 1,
                dueOn: '05/08',
                overdue: true,
            },
        ]);

        expect(screen.getByText('Autovalores')).toBeInTheDocument();
        // Modo silencioso: a fila não anuncia o atraso durante o foco.
        expect(screen.queryByText('1 atrasada')).not.toBeInTheDocument();
    });

    it('preselects the first due review, ahead of the next task in the track', () => {
        const twoSubjects: StudyPlanSummary = {
            id: 1,
            name: 'Concurso XYZ',
            subjects: [
                ...plan.subjects,
                {
                    id: 11,
                    name: 'Estatística',
                    topics: [
                        {
                            id: 110,
                            name: 'Distribuições',
                            cycle: 0,
                            position: 1,
                            completed: true,
                            reviewStatus: 'overdue',
                        },
                    ],
                },
            ],
        };

        render(
            <FocusShow
                plans={[]}
                plan={twoSubjects}
                session={null}
                pomodoroSetting={pomodoroSetting}
                todayPomodoros={0}
                today={{ minutes: 0, goalMinutes: 100 }}
                dueReviews={[
                    {
                        id: 1,
                        subjectId: 11,
                        subjectName: 'Estatística',
                        topicId: 110,
                        topicName: 'Distribuições',
                        sequence: 1,
                        dueOn: '05/08',
                        overdue: true,
                    },
                ]}
            />,
        );

        // A trilha apontaria para Álgebra Linear (posição 0, em aberto); a
        // fila de revisões é quem manda quando há revisão devida.
        expect(
            screen.getByLabelText('Disciplina (opcional)'),
        ).toHaveTextContent('Estatística');
    });

    it('lets the review queue speak up during a break', () => {
        renderFocus(session({ phase: 'short_break' }), [
            {
                id: 1,
                subjectId: 10,
                subjectName: 'Álgebra Linear',
                topicId: 100,
                topicName: 'Autovalores',
                sequence: 1,
                dueOn: '05/08',
                overdue: true,
            },
        ]);

        expect(screen.getByText('1 atrasada')).toBeInTheDocument();
    });

    it('paints the zen mode with the colour the user picked', async () => {
        const user = userEvent.setup();
        const { container } = renderFocus(session());

        await user.click(screen.getByRole('button', { name: 'Modo zen' }));

        const overlay = container.querySelector(
            'div[data-zen-theme]:not([role="group"] *)',
        ) as HTMLElement;

        expect(overlay).toHaveAttribute('data-zen-theme', 'coral');

        await user.click(screen.getByRole('button', { name: 'Índigo' }));

        expect(overlay).toHaveAttribute('data-zen-theme', 'indigo');
        expect(overlay.style.getPropertyValue('--zen-hue')).toBe('285');
    });

    it('keeps the shortcuts in sight inside the zen mode', async () => {
        const user = userEvent.setup();
        renderFocus(session());

        await user.click(screen.getByRole('button', { name: 'Modo zen' }));

        expect(
            screen.getByText(/Espaço pausa · T oculta o tempo · E encerra/),
        ).toBeInTheDocument();
    });

    it('hides the clock everywhere it would otherwise leak, tab title included', async () => {
        const user = userEvent.setup();
        renderFocus(session({ elapsedSeconds: 600 }));

        expect(document.title).toContain('40:00');

        await user.click(screen.getByRole('button', { name: 'Ocultar tempo' }));

        expect(screen.queryByRole('timer')).not.toBeInTheDocument();
        expect(document.title).not.toContain('40:00');
        expect(document.title).toContain('Álgebra Linear');
        expect(
            screen.getByRole('button', { name: 'Mostrar tempo' }),
        ).toBeInTheDocument();
    });

    it('takes T as the shortcut for hiding the time', async () => {
        const user = userEvent.setup();
        renderFocus(session({ elapsedSeconds: 600 }));

        await user.keyboard('t');

        expect(screen.queryByRole('timer')).not.toBeInTheDocument();

        await user.keyboard('t');

        expect(screen.getByRole('timer')).toBeInTheDocument();
    });

    it('closes the session in one click when the log form is turned off', () => {
        renderFocus(session(), [], {
            ...pomodoroSetting,
            logSessionOnFinish: false,
        });

        expect(
            screen.getByRole('button', { name: 'Encerrar sessão' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByText('Registrar a sessão'),
        ).not.toBeInTheDocument();
    });
});
