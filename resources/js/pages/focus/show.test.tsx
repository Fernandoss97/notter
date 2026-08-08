import { act, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import FocusShow from './show';
import type {
    DueReview,
    FocusSession,
    PomodoroSettingSummary,
    StudyPlanSummary,
} from '@/types/study';

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
    requestNotificationPermission: vi.fn(),
}));

const plan: StudyPlanSummary = {
    id: 1,
    name: 'Concurso XYZ',
    subjects: [
        {
            id: 10,
            name: 'Álgebra Linear',
            priority: 'high',
            topics: [
                {
                    id: 100,
                    name: 'Autovalores',
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

    it('closes the cycle exactly once when the clock runs out', () => {
        vi.useFakeTimers();
        // Falta 1 segundo: o efeito de fim de ciclo ainda não pode disparar.
        renderFocus(session({ elapsedSeconds: 2999 }));

        expect(post).not.toHaveBeenCalled();

        act(() => {
            vi.advanceTimersByTime(5000);
        });

        const advances = post.mock.calls.filter(
            ([url]) => url === '/foco/advance',
        );

        // Cinco ticks correram depois da expiração e o servidor (mockado)
        // nunca respondeu: o fim do ciclo vale um POST, não um por tick.
        expect(advances).toHaveLength(1);
        expect(announcePhaseEnd).toHaveBeenCalledOnce();
    });

    it('does not close a cycle that still has time left', () => {
        vi.useFakeTimers();
        renderFocus(session({ elapsedSeconds: 10 }));

        act(() => {
            vi.advanceTimersByTime(5000);
        });

        expect(post).not.toHaveBeenCalled();
        expect(announcePhaseEnd).not.toHaveBeenCalled();
    });

    it('announces the end of a break with its own wording', () => {
        vi.useFakeTimers();
        renderFocus(
            session({
                phase: 'short_break',
                elapsedSeconds: 600,
                plannedSeconds: 600,
            }),
        );

        expect(announcePhaseEnd).toHaveBeenCalledWith(
            'Intervalo terminado',
            expect.stringContaining('voltar ao foco'),
        );
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
