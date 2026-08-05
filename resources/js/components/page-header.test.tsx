import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { PageHeader } from './page-header';

describe('PageHeader', () => {
    it('renders the title', () => {
        render(<PageHeader title="Plano de estudos" />);

        expect(
            screen.getByRole('heading', { name: 'Plano de estudos' }),
        ).toBeInTheDocument();
    });

    it('renders the subtitle when provided', () => {
        render(
            <PageHeader
                title="Plano de estudos"
                subtitle="Estudo livre · 5 disciplinas"
            />,
        );

        expect(
            screen.getByText('Estudo livre · 5 disciplinas'),
        ).toBeInTheDocument();
    });

    it('renders actions when provided', () => {
        render(
            <PageHeader
                title="Plano de estudos"
                actions={<button>Editar plano</button>}
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Editar plano' }),
        ).toBeInTheDocument();
    });
});
