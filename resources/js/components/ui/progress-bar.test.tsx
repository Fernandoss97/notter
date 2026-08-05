import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { ProgressBar } from './progress-bar';

describe('ProgressBar', () => {
    it('exposes the current value on the progressbar role', () => {
        render(<ProgressBar value={42} />);

        const bar = screen.getByRole('progressbar');
        expect(bar).toHaveAttribute('aria-valuenow', '42');
    });

    it('clamps values outside the 0-100 range', () => {
        render(<ProgressBar value={150} />);

        expect(screen.getByRole('progressbar')).toHaveAttribute(
            'aria-valuenow',
            '100',
        );
    });

    it('shows the percentage label when requested', () => {
        render(<ProgressBar value={60} showLabel />);

        expect(screen.getByText('60%')).toBeInTheDocument();
    });

    it('applies the semantic color variant class', () => {
        render(<ProgressBar value={50} color="success" />);

        expect(screen.getByRole('progressbar')).toHaveClass(
            'bg-status-success',
        );
    });
});
