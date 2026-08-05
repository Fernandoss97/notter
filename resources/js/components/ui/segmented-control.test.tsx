import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { SegmentedControl } from './segmented-control';

const OPTIONS = [
    { value: 'reading', label: 'Leitura' },
    { value: 'exercises', label: 'Exercícios' },
    { value: 'review', label: 'Revisão' },
];

describe('SegmentedControl', () => {
    it('marks the active option as pressed', () => {
        render(
            <SegmentedControl
                options={OPTIONS}
                value="exercises"
                onValueChange={vi.fn()}
            />,
        );

        expect(
            screen.getByRole('radio', { name: 'Exercícios' }),
        ).toHaveAttribute('data-state', 'on');
        expect(
            screen.getByRole('radio', { name: 'Leitura' }),
        ).toHaveAttribute('data-state', 'off');
    });

    it('calls onValueChange when a different option is clicked', async () => {
        const onValueChange = vi.fn();
        render(
            <SegmentedControl
                options={OPTIONS}
                value="exercises"
                onValueChange={onValueChange}
            />,
        );

        await userEvent.click(screen.getByRole('radio', { name: 'Revisão' }));

        expect(onValueChange).toHaveBeenCalledWith('review');
    });

    it('does not call onValueChange when clicking the already-active option', async () => {
        const onValueChange = vi.fn();
        render(
            <SegmentedControl
                options={OPTIONS}
                value="exercises"
                onValueChange={onValueChange}
            />,
        );

        await userEvent.click(
            screen.getByRole('radio', { name: 'Exercícios' }),
        );

        expect(onValueChange).not.toHaveBeenCalled();
    });
});
