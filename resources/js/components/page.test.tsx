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
});
