import { describe, expect, it } from 'vitest';
import { formatClock, formatDurationMinutes } from './format-duration';

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

describe('formatClock', () => {
    it('pads minutes and seconds', () => {
        expect(formatClock(65)).toBe('01:05');
    });

    it('grows an hours field for long sessions instead of overflowing minutes', () => {
        expect(formatClock(3 * 3600 + 23)).toBe('3:00:23');
        expect(formatClock(10823)).toBe('3:00:23');
    });

    it('never renders negative time', () => {
        expect(formatClock(-5)).toBe('00:00');
    });

    it('truncates fractional seconds', () => {
        expect(formatClock(59.9)).toBe('00:59');
    });
});
