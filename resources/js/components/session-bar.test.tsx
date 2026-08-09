import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { ActiveFocusSession } from '@/types/session';
import { SessionBar } from './session-bar';

const { usePage, post, announcePhaseEnd } = vi.hoisted(() => ({
    usePage: vi.fn(),
    post: vi.fn(),
    announcePhaseEnd: vi.fn(),
}));

vi.mock('@/lib/focus-alerts', () => ({
    announcePhaseEnd,
    requestNotificationPermission: vi.fn(),
}));

vi.mock('@inertiajs/react', () => ({
    usePage,
    router: { post },
    Link: ({
        href,
        children,
        ...props
    }: {
        href: string | { url: string };
        children: React.ReactNode;
    }) => (
        <a href={typeof href === 'string' ? href : href.url} {...props}>
            {children}
        </a>
    ),
}));

function session(
    overrides: Partial<ActiveFocusSession> = {},
): ActiveFocusSession {
    return {
        id: 1,
        subject: 'Álgebra Linear',
        topic: 'Autovalores e autovetores',
        state: 'running',
        phase: 'focus',
        completedCycles: 0,
        elapsedSeconds: 125,
        plannedSeconds: 3000,
        ...overrides,
    };
}

function mockActiveSession(
    active: ActiveFocusSession | null,
    url: string = '/dashboard',
) {
    usePage.mockReturnValue({ props: { activeSession: active }, url });
}

describe('SessionBar', () => {
    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
        post.mockReset();
        announcePhaseEnd.mockReset();
    });

    it('renders no session content when there is no active session', () => {
        mockActiveSession(null);

        const { container } = render(<SessionBar />);

        // O invólucro fica montado (colapsado) para que a entrada da barra
        // possa ser animada em vez de empurrar a página em 64px de uma vez.
        expect(screen.queryByText('Em foco')).not.toBeInTheDocument();
        expect(
            container.querySelector('[data-slot="session-bar"]'),
        ).toHaveAttribute('data-visible', 'false');
    });

    it('shows the running session subject, topic and remaining time', () => {
        mockActiveSession(session());

        render(<SessionBar />);

        expect(screen.getByText('Em foco')).toBeInTheDocument();
        // Regressivo como o cronômetro da tela de foco: 3000 - 125.
        expect(screen.getByText('47:55')).toBeInTheDocument();
        expect(
            screen.getByText('Álgebra Linear · Autovalores e autovetores'),
        ).toBeInTheDocument();
    });

    it('never shows negative time when the phase overruns', () => {
        mockActiveSession(session({ elapsedSeconds: 3200 }));

        render(<SessionBar />);

        expect(screen.getByText('00:00')).toBeInTheDocument();
    });

    it('collapses and hides itself from assistive tech on the focus screen', () => {
        mockActiveSession(session(), '/foco');

        const { container } = render(<SessionBar />);

        const bar = container.querySelector('[data-slot="session-bar"]');
        expect(bar).toHaveAttribute('data-visible', 'false');
        expect(bar?.firstElementChild).toHaveAttribute('aria-hidden', 'true');
        expect(bar?.firstElementChild).toHaveAttribute('inert');
        expect(
            screen.queryByRole('link', { name: 'Abrir sessão' }),
        ).not.toBeInTheDocument();
    });

    it('is expanded and reachable outside the focus screen', () => {
        mockActiveSession(session());

        const { container } = render(<SessionBar />);

        const bar = container.querySelector('[data-slot="session-bar"]');
        expect(bar).toHaveAttribute('data-visible', 'true');
        expect(bar?.firstElementChild).not.toHaveAttribute('inert');
    });

    it('shows the paused label for a paused session', () => {
        mockActiveSession(session({ state: 'paused' }));

        render(<SessionBar />);

        expect(screen.getByText('Pausado')).toBeInTheDocument();
    });

    it('calls a running break an interval, not focus', () => {
        mockActiveSession(session({ phase: 'short_break' }));

        render(<SessionBar />);

        expect(screen.getByText('Intervalo')).toBeInTheDocument();
        expect(screen.queryByText('Em foco')).not.toBeInTheDocument();
    });

    it('links to the focus screen', () => {
        mockActiveSession(session());

        render(<SessionBar />);

        expect(
            screen.getByRole('link', { name: 'Abrir sessão' }),
        ).toHaveAttribute('href', '/foco');
    });

    it('pauses without leaving the current page', async () => {
        mockActiveSession(session());

        render(<SessionBar />);

        await userEvent.click(screen.getByRole('button', { name: 'Pausar' }));

        // `router.post` está mockado e nunca devolve props novas: se o
        // rótulo dependesse do servidor, continuaria em "Pausar".
        expect(post).toHaveBeenCalledOnce();
        expect(
            screen.getByRole('button', { name: 'Retomar' }),
        ).toBeInTheDocument();
    });

    it('grows an hours field instead of overflowing the minutes', () => {
        // Uma pausa longa de 4h com 59min corridos: faltam 3:00:23.
        mockActiveSession(
            session({ elapsedSeconds: 3577, plannedSeconds: 14400 }),
        );

        render(<SessionBar />);

        expect(screen.getByText('3:00:23')).toBeInTheDocument();
    });

    it('closes the cycle from any screen, exactly once', () => {
        vi.useFakeTimers();
        // A barra roda no /plano: sem ela, o tempo zeraria e nada
        // aconteceria até o usuário voltar à tela de foco.
        mockActiveSession(
            session({ elapsedSeconds: 59, plannedSeconds: 60 }),
            '/plano',
        );

        render(<SessionBar />);
        expect(post).not.toHaveBeenCalled();

        act(() => {
            vi.advanceTimersByTime(5000);
        });

        expect(
            post.mock.calls.filter(([url]) => url === '/foco/advance'),
        ).toHaveLength(1);
        expect(announcePhaseEnd).toHaveBeenCalledOnce();
    });

    it('does not close a cycle that still has time left', () => {
        vi.useFakeTimers();
        mockActiveSession(session({ elapsedSeconds: 10, plannedSeconds: 600 }));

        render(<SessionBar />);

        act(() => {
            vi.advanceTimersByTime(5000);
        });

        expect(post).not.toHaveBeenCalled();
        expect(announcePhaseEnd).not.toHaveBeenCalled();
    });

    it('announces the end of a break with its own wording', () => {
        mockActiveSession(
            session({
                phase: 'short_break',
                elapsedSeconds: 600,
                plannedSeconds: 600,
            }),
        );

        render(<SessionBar />);

        expect(announcePhaseEnd).toHaveBeenCalledWith(
            'Intervalo terminado',
            expect.stringContaining('voltar ao foco'),
        );
    });

    it('drops the pause button once the time is up', () => {
        mockActiveSession(session({ elapsedSeconds: 3000 }));

        render(<SessionBar />);

        // Não há o que pausar: o ciclo acabou e o avanço já foi pedido.
        expect(
            screen.queryByRole('button', { name: 'Pausar' }),
        ).not.toBeInTheDocument();
        expect(screen.getByText('Ciclo concluído')).toBeInTheDocument();
    });

    it('counts the remaining time down once a second while running', () => {
        vi.useFakeTimers();
        mockActiveSession(session({ elapsedSeconds: 0, plannedSeconds: 60 }));

        render(<SessionBar />);

        expect(screen.getByText('01:00')).toBeInTheDocument();

        act(() => {
            vi.advanceTimersByTime(3000);
        });

        expect(screen.getByText('00:57')).toBeInTheDocument();
    });
});
