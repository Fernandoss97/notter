import { describe, expect, it } from 'vitest';
import { formatDurationMinutes } from './format-duration';

describe('formatDurationMinutes', () => {
    it('formats minutes under an hour', () => {
        expect(formatDurationMinutes(45)).toBe('45min');
    });

    it('formats hours and minutes', () => {
        expect(formatDurationMinutes(100)).toBe('1h 40min');
    });

    it('formats a whole number of hours', () => {
        expect(formatDurationMinutes(120)).toBe('2h 0min');
    });

    it('formats zero minutes', () => {
        expect(formatDurationMinutes(0)).toBe('0min');
    });
});
