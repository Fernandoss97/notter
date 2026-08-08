import { act, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { ActiveFocusSession } from '@/types/session';
import { SessionBar } from './session-bar';

const { usePage } = vi.hoisted(() => ({ usePage: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    usePage,
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

function mockActiveSession(
    session: ActiveFocusSession | null,
    url: string = '/dashboard',
) {
    usePage.mockReturnValue({ props: { activeSession: session }, url });
}

describe('SessionBar', () => {
    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    it('renders nothing when there is no active session', () => {
        mockActiveSession(null);

        const { container } = render(<SessionBar />);

        expect(container).toBeEmptyDOMElement();
    });

    it('shows the running session subject, topic and elapsed time', () => {
        mockActiveSession({
            id: 1,
            subject: 'Álgebra Linear',
            topic: 'Autovalores e autovetores',
            state: 'running',
            elapsedSeconds: 125,
            plannedSeconds: 3000,
        });

        render(<SessionBar />);

        expect(screen.getByText('Em foco')).toBeInTheDocument();
        expect(screen.getByText('02:05')).toBeInTheDocument();
        expect(
            screen.getByText('Álgebra Linear · Autovalores e autovetores'),
        ).toBeInTheDocument();
    });

    it('renders nothing while already on the focus screen', () => {
        mockActiveSession(
            {
                id: 1,
                subject: 'Álgebra Linear',
                topic: 'Autovalores e autovetores',
                state: 'running',
                elapsedSeconds: 125,
                plannedSeconds: 3000,
            },
            '/foco',
        );

        const { container } = render(<SessionBar />);

        expect(container).toBeEmptyDOMElement();
    });

    it('shows the paused label for a paused session', () => {
        mockActiveSession({
            id: 1,
            subject: 'Espanhol',
            topic: 'Verbos irregulares',
            state: 'paused',
            elapsedSeconds: 60,
            plannedSeconds: 1500,
        });

        render(<SessionBar />);

        expect(screen.getByText('Pausado')).toBeInTheDocument();
    });

    it('links to the focus screen', () => {
        mockActiveSession({
            id: 1,
            subject: 'Espanhol',
            topic: 'Verbos irregulares',
            state: 'running',
            elapsedSeconds: 60,
            plannedSeconds: 1500,
        });

        render(<SessionBar />);

        expect(
            screen.getByRole('link', { name: 'Abrir sessão' }),
        ).toHaveAttribute('href', '/foco');
    });

    it('ticks the elapsed time forward once a second while running', () => {
        vi.useFakeTimers();
        mockActiveSession({
            id: 1,
            subject: 'Cálculo II',
            topic: 'Integrais',
            state: 'running',
            elapsedSeconds: 0,
            plannedSeconds: 3000,
        });

        render(<SessionBar />);

        expect(screen.getByText('00:00')).toBeInTheDocument();

        act(() => {
            vi.advanceTimersByTime(3000);
        });

        expect(screen.getByText('00:03')).toBeInTheDocument();
    });
});
