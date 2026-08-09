import { act, renderHook } from '@testing-library/react';
import { createElement } from 'react';
import { renderToString } from 'react-dom/server';
import { beforeEach, describe, expect, it } from 'vitest';
import { createLocalPreference, usePreference } from './local-preference';

function makePreference(key = 'test-preference') {
    return createLocalPreference<'a' | 'b'>({
        key,
        fallback: 'a',
        parse: (stored) => (stored === 'a' || stored === 'b' ? stored : null),
        serialize: (value) => value,
    });
}

describe('createLocalPreference', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    it('starts on the fallback', () => {
        const preference = makePreference();

        expect(preference.get()).toBe('a');
    });

    it('reads back what an earlier session stored', () => {
        localStorage.setItem('stored-preference', 'b');
        const preference = makePreference('stored-preference');

        expect(preference.get()).toBe('b');
    });

    it('ignores a stored value that is no longer valid', () => {
        localStorage.setItem('stale-preference', 'z');
        const preference = makePreference('stale-preference');

        expect(preference.get()).toBe('a');
    });

    it('keeps every mounted consumer in sync', () => {
        const preference = makePreference();
        const first = renderHook(() => usePreference(preference));
        const second = renderHook(() => usePreference(preference));

        act(() => first.result.current[1]('b'));

        expect(second.result.current[0]).toBe('b');
        expect(localStorage.getItem('test-preference')).toBe('b');
    });

    it('renders on the server with what the server knew', () => {
        const preference = makePreference('server-preference');
        const Probe = () =>
            createElement('span', null, usePreference(preference, 'b')[0]);

        // No servidor não há `localStorage`: o valor vem do cookie, via
        // props. Sem isso o HTML sairia com o padrão e a preferência só
        // valeria depois da hidratação — que é o flash a ser evitado.
        expect(renderToString(createElement(Probe))).toContain('b');
    });

    it('falls back on the server when nothing was passed', () => {
        const preference = makePreference('serverless-preference');
        const Probe = () =>
            createElement('span', null, usePreference(preference)[0]);

        expect(renderToString(createElement(Probe))).toContain('a');
    });

    it('survives the component that changed it being unmounted', () => {
        const preference = makePreference();
        const { result, unmount } = renderHook(() => usePreference(preference));

        act(() => result.current[1]('b'));
        unmount();

        expect(preference.get()).toBe('b');
    });
});
