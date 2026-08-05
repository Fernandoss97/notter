import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Dot } from './dot';

describe('Dot', () => {
    it('renders a small rounded marker', () => {
        const { container } = render(<Dot />);

        const dot = container.querySelector('span');
        expect(dot).toHaveClass('rounded-full', 'size-2');
    });

    it('merges a custom color class', () => {
        const { container } = render(<Dot className="bg-focus-500" />);

        expect(container.querySelector('span')).toHaveClass('bg-focus-500');
    });
});
