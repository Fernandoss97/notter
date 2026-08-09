import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { ZEN_THEMES } from '@/lib/zen-themes';
import { ZenThemePicker } from './zen-theme-picker';

describe('ZenThemePicker', () => {
    it('offers every theme by name', () => {
        render(<ZenThemePicker value="coral" onValueChange={vi.fn()} />);

        for (const theme of ZEN_THEMES) {
            expect(
                screen.getByRole('button', { name: theme.label }),
            ).toBeInTheDocument();
        }
    });

    it('marks the theme in use', () => {
        render(<ZenThemePicker value="azul" onValueChange={vi.fn()} />);

        expect(screen.getByRole('button', { name: 'Azul' })).toHaveAttribute(
            'aria-pressed',
            'true',
        );
        expect(screen.getByRole('button', { name: 'Coral' })).toHaveAttribute(
            'aria-pressed',
            'false',
        );
    });

    it('reports the chosen theme', async () => {
        const onValueChange = vi.fn();
        render(<ZenThemePicker value="coral" onValueChange={onValueChange} />);

        await userEvent.click(screen.getByRole('button', { name: 'Verde' }));

        expect(onValueChange).toHaveBeenCalledWith('verde');
    });

    it('paints each swatch with the theme it stands for', () => {
        render(<ZenThemePicker value="coral" onValueChange={vi.fn()} />);

        // A amostra carrega o próprio `data-zen-theme`: é o mesmo CSS que
        // pinta o modo zen que a pinta, inclusive no tema escuro.
        const swatch = screen.getByRole('button', { name: 'Magenta' });

        expect(swatch).toHaveAttribute('data-zen-theme', 'magenta');
        expect(swatch.style.getPropertyValue('--zen-hue')).toBe('325');
    });
});
