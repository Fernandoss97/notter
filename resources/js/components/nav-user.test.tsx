import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { SidebarProvider } from '@/components/ui/sidebar';
import { TooltipProvider } from '@/components/ui/tooltip';
import { NavUser } from './nav-user';

const { flushAll, pageProps } = vi.hoisted(() => {
    // `use-mobile` consulta matchMedia no carregamento do módulo, então o stub
    // precisa existir antes dos imports — daí o vi.hoisted.
    window.matchMedia = vi.fn().mockImplementation((query: string) => ({
        matches: false,
        media: query,
        onchange: null,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        addListener: vi.fn(),
        removeListener: vi.fn(),
        dispatchEvent: vi.fn(),
    }));

    return {
        flushAll: vi.fn(),
        pageProps: {
            url: '/foco',
            current: {
                auth: {
                    user: {
                        id: 1,
                        name: 'Fernando',
                        email: 'fernando@example.com',
                    },
                },
            } as Record<string, unknown>,
        },
    };
});

vi.mock('@inertiajs/react', () => ({
    Link: ({
        children,
        href,
        ...props
    }: {
        children: React.ReactNode;
        href: { url: string } | string;
    }) => (
        <a {...props} href={typeof href === 'string' ? href : href.url}>
            {children}
        </a>
    ),
    router: { flushAll },
    usePage: () => ({ props: pageProps.current, url: pageProps.url }),
}));

function renderNavUser() {
    return render(
        <TooltipProvider>
            <SidebarProvider>
                <NavUser />
            </SidebarProvider>
        </TooltipProvider>,
    );
}

describe('NavUser', () => {
    it('mostra o usuário e o sair direto na sidebar, sem dropdown', () => {
        renderNavUser();

        expect(screen.getByText('Fernando')).toBeInTheDocument();
        expect(screen.getByText('Sair')).toBeInTheDocument();
        expect(screen.queryByRole('menu')).not.toBeInTheDocument();
        expect(
            document.querySelector('[data-slot="dropdown-menu-trigger"]'),
        ).toBeNull();
    });

    it('leva o item do usuário para as configurações', () => {
        renderNavUser();

        expect(screen.getByRole('link', { name: 'Fernando' })).toHaveAttribute(
            'href',
            '/settings/profile',
        );
    });

    it('não renderiza nada quando não há usuário autenticado', () => {
        pageProps.current = { auth: { user: null } };

        const { container } = renderNavUser();

        expect(
            container.querySelector('[data-test="logout-button"]'),
        ).toBeNull();
    });
});
