import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import SettingsLayout from './layout';

const { page } = vi.hoisted(() => ({
    page: { url: '/settings/profile' },
}));

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
    usePage: () => page,
}));

function renderLayout(url: string) {
    page.url = url;

    return render(
        <SettingsLayout>
            <p>conteúdo</p>
        </SettingsLayout>,
    );
}

describe('SettingsLayout', () => {
    it('lista as telas de configuração, incluindo o pomodoro', () => {
        renderLayout('/settings/profile');

        expect(
            screen.getByRole('link', { name: 'Perfil' }),
        ).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Pomodoro' })).toHaveAttribute(
            'href',
            '/configuracoes',
        );
        expect(
            screen.getByRole('link', { name: 'Segurança' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Aparência' }),
        ).toBeInTheDocument();
        expect(screen.getByText('conteúdo')).toBeInTheDocument();
    });

    it('marca como atual apenas a tela aberta', () => {
        renderLayout('/configuracoes');

        expect(screen.getByRole('link', { name: 'Pomodoro' })).toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(
            screen.getByRole('link', { name: 'Perfil' }),
        ).not.toHaveAttribute('aria-current');
    });
});
