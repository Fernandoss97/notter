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

    // Regressão: a lista de transição não incluía `background-color`, então
    // todo `hover:bg-*` das variantes trocava de cor sem transição alguma.
    it('transitions the properties its variants actually change', () => {
        render(<Button>Save</Button>);

        const button = screen.getByRole('button', { name: 'Save' });
        expect(button).toHaveClass(
            'transition-[color,background-color,border-color,box-shadow,scale]',
        );
        expect(button).toHaveClass('duration-fast');
    });

    it('gives immediate press feedback on :active', () => {
        render(<Button>Save</Button>);

        const button = screen.getByRole('button', { name: 'Save' });
        expect(button).toHaveClass('active:scale-[0.98]');
        expect(button).toHaveClass('active:duration-instant');
    });
});
