import { renderHook } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { useDocumentTitle } from './use-document-title';

describe('useDocumentTitle', () => {
    it('writes the title while mounted', () => {
        renderHook(() => useDocumentTitle('25:00 · Álgebra Linear'));

        expect(document.title).toBe('25:00 · Álgebra Linear');
    });

    it('restores the previous title on unmount', () => {
        document.title = 'Foco';

        const { unmount } = renderHook(() => useDocumentTitle('24:59 · Foco'));
        unmount();

        expect(document.title).toBe('Foco');
    });

    it('leaves the title alone when given null', () => {
        document.title = 'Foco';

        renderHook(() => useDocumentTitle(null));

        expect(document.title).toBe('Foco');
    });
});
