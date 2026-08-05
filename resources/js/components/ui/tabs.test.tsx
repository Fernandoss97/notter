import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { Tabs } from './tabs';

const ITEMS = [
    { value: 'subjects', label: 'Disciplinas' },
    { value: 'schedule', label: 'Cronograma semanal' },
];

describe('Tabs', () => {
    it('marks the active tab as selected', () => {
        render(
            <Tabs items={ITEMS} value="subjects" onValueChange={vi.fn()} />,
        );

        expect(
            screen.getByRole('tab', { name: 'Disciplinas' }),
        ).toHaveAttribute('aria-selected', 'true');
        expect(
            screen.getByRole('tab', { name: 'Cronograma semanal' }),
        ).toHaveAttribute('aria-selected', 'false');
    });

    it('calls onValueChange when a tab is clicked', async () => {
        const onValueChange = vi.fn();
        render(
            <Tabs
                items={ITEMS}
                value="subjects"
                onValueChange={onValueChange}
            />,
        );

        await userEvent.click(
            screen.getByRole('tab', { name: 'Cronograma semanal' }),
        );

        expect(onValueChange).toHaveBeenCalledWith('schedule');
    });
});
