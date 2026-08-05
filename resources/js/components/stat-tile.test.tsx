import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { StatTile } from './stat-tile';

describe('StatTile', () => {
    it('renders the label, value and unit', () => {
        render(<StatTile label="Horas hoje" value="3h 12" unit="min" />);

        expect(screen.getByText('Horas hoje')).toBeInTheDocument();
        expect(screen.getByText('3h 12')).toBeInTheDocument();
        expect(screen.getByText('min')).toBeInTheDocument();
    });

    it('renders the hint when provided', () => {
        render(
            <StatTile
                label="Sequência"
                value="23"
                hint="maior sequência: 31"
            />,
        );

        expect(screen.getByText('maior sequência: 31')).toBeInTheDocument();
    });

    it('applies the semantic color variant to the value', () => {
        render(<StatTile label="Semana" value="18h 40" color="success" />);

        expect(screen.getByText('18h 40')).toHaveClass('text-status-success');
    });

    it('defaults to the foreground color', () => {
        render(<StatTile label="Tópicos" value="46" />);

        expect(screen.getByText('46')).toHaveClass('text-foreground');
    });
});
