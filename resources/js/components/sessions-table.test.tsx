import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { SessionsTable } from './sessions-table';

describe('SessionsTable', () => {
    it('renders a row per session', () => {
        render(
            <SessionsTable
                rows={[
                    {
                        date: '01/08',
                        subject: 'Álgebra Linear',
                        duration: '50min',
                        type: 'Exercícios',
                        exercises: 20,
                        accuracy: 80,
                    },
                ]}
            />,
        );

        expect(screen.getByText('Álgebra Linear')).toBeInTheDocument();
        expect(screen.getByText('50min')).toBeInTheDocument();
        expect(screen.getByText('20')).toBeInTheDocument();
        expect(screen.getByText('80%')).toBeInTheDocument();
    });

    it('renders a placeholder for sessions without exercises or accuracy', () => {
        render(
            <SessionsTable
                rows={[
                    {
                        date: '01/08',
                        subject: 'Redação',
                        duration: '30min',
                        type: 'Leitura',
                        exercises: null,
                        accuracy: null,
                    },
                ]}
            />,
        );

        const dashes = screen.getAllByText('—');
        expect(dashes).toHaveLength(2);
    });

    it('renders nothing in the body when there are no rows', () => {
        const { container } = render(<SessionsTable rows={[]} />);

        expect(container.querySelectorAll('tbody tr')).toHaveLength(0);
    });
});
