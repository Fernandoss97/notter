import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { Button } from './button';

describe('Button', () => {
    it('renders children and responds to clicks', async () => {
        const onClick = vi.fn();
        render(<Button onClick={onClick}>Save</Button>);

        const button = screen.getByRole('button', { name: 'Save' });
        await userEvent.click(button);

        expect(onClick).toHaveBeenCalledOnce();
    });

    it('applies the destructive variant classes', () => {
        render(<Button variant="destructive">Delete</Button>);

        expect(screen.getByRole('button', { name: 'Delete' })).toHaveClass(
            'bg-destructive',
        );
    });

    it('is disabled when the disabled prop is set', () => {
        render(<Button disabled>Save</Button>);

        expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled();
    });
});
