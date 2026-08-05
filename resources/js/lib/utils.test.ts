import { describe, expect, it } from 'vitest';
import { cn, toUrl } from './utils';

describe('cn', () => {
    it('merges class names and resolves Tailwind conflicts', () => {
        expect(cn('px-2 py-1', 'px-4')).toBe('py-1 px-4');
    });

    it('drops falsy values', () => {
        expect(cn('a', false, undefined, null, 'b')).toBe('a b');
    });
});

describe('toUrl', () => {
    it('returns string hrefs unchanged', () => {
        expect(toUrl('/dashboard')).toBe('/dashboard');
    });

    it('extracts the url from an Inertia link object', () => {
        expect(toUrl({ url: '/settings', method: 'get' })).toBe('/settings');
    });
});
