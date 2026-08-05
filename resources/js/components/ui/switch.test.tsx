import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { Switch } from './switch';

describe('Switch', () => {
    it('reflects the checked state via aria-checked', () => {
        render(<Switch checked onCheckedChange={vi.fn()} />);

        expect(screen.getByRole('switch')).toHaveAttribute(
            'aria-checked',
            'true',
        );
    });

    it('calls onCheckedChange with the toggled value on click', async () => {
        const onCheckedChange = vi.fn();
        render(<Switch checked={false} onCheckedChange={onCheckedChange} />);

        await userEvent.click(screen.getByRole('switch'));

        expect(onCheckedChange).toHaveBeenCalledWith(true);
    });

    it('does not call onCheckedChange when disabled', async () => {
        const onCheckedChange = vi.fn();
        render(
            <Switch
                checked={false}
                onCheckedChange={onCheckedChange}
                disabled
            />,
        );

        await userEvent.click(screen.getByRole('switch'));

        expect(onCheckedChange).not.toHaveBeenCalled();
    });
});
