import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Panel } from './panel';

describe('Panel', () => {
    it('renders the title and children', () => {
        render(<Panel title="Disciplinas do plano">Conteúdo</Panel>);

        expect(screen.getByText('Disciplinas do plano')).toBeInTheDocument();
        expect(screen.getByText('Conteúdo')).toBeInTheDocument();
    });

    it('renders the action alongside the title', () => {
        render(
            <Panel title="Sessões" action={<button>Ver tudo</button>}>
                Conteúdo
            </Panel>,
        );

        expect(
            screen.getByRole('button', { name: 'Ver tudo' }),
        ).toBeInTheDocument();
    });

    it('omits the header entirely when no title or action is given', () => {
        const { container } = render(<Panel>Conteúdo</Panel>);

        expect(container.querySelector('header')).not.toBeInTheDocument();
    });

    it('removes body padding when pad is false', () => {
        const { container } = render(<Panel pad={false}>Conteúdo</Panel>);

        const body = screen.getByText('Conteúdo').parentElement;
        expect(body).not.toHaveClass('p-5');
        expect(container).toBeTruthy();
    });
});
