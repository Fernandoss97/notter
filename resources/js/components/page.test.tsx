import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Page } from './page';

describe('Page', () => {
    it('renders its children inside the constrained container', () => {
        render(
            <Page>
                <p>Conteúdo</p>
            </Page>,
        );

        expect(screen.getByText('Conteúdo')).toBeInTheDocument();
    });

    it('narrows the container when maxWidth is "narrow"', () => {
        render(
            <Page maxWidth="narrow">
                <p>Conteúdo</p>
            </Page>,
        );

        expect(screen.getByText('Conteúdo').parentElement).toHaveClass(
            'max-w-4xl',
        );
    });
});
