import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { ReviewStatusBadge } from './review-status-badge';

describe('ReviewStatusBadge', () => {
    it('renders the default label for a status', () => {
        render(<ReviewStatusBadge status="overdue" />);

        expect(screen.getByText('Atrasada')).toBeInTheDocument();
    });

    it('renders a custom label when provided', () => {
        render(
            <ReviewStatusBadge
                status="overdue"
                label="Álgebra Linear · revisão atrasada"
            />,
        );

        expect(
            screen.getByText('Álgebra Linear · revisão atrasada'),
        ).toBeInTheDocument();
    });

    it('applies the semantic color classes for each status', () => {
        render(<ReviewStatusBadge status="goalHit" />);

        expect(screen.getByText('Meta batida')).toHaveClass(
            'bg-status-info-bg',
            'text-status-info',
        );
    });
});
