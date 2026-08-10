import { describe, expect, it } from 'vitest';
import type { CycleSlotSummary, SubjectArea } from '@/types/study';
import { findAreaClashes } from './cycle-interleaving';

function slot(
    name: string,
    area: SubjectArea,
    state: CycleSlotSummary['state'] = 'active',
): CycleSlotSummary {
    return {
        id: name.length,
        subjectId: name.length,
        subjectName: name,
        subjectMode: 'reading',
        subjectArea: area,
        blockMinutes: 60,
        effectiveBlockMinutes: 60,
        boosted: false,
        state,
        isCurrent: false,
        block: null,
    };
}

describe('findAreaClashes', () => {
    it('finds two subjects of the same family in a row', () => {
        expect(
            findAreaClashes([
                slot('Constitucional', 'law'),
                slot('Administrativo', 'law'),
            ]),
        ).toEqual(['Constitucional e Administrativo']);
    });

    it('says nothing when the families alternate', () => {
        expect(
            findAreaClashes([
                slot('Constitucional', 'law'),
                slot('Português', 'languages'),
                slot('Administrativo', 'law'),
            ]),
        ).toEqual([]);
    });

    it('reports every pair in a run of three', () => {
        expect(
            findAreaClashes([
                slot('Constitucional', 'law'),
                slot('Administrativo', 'law'),
                slot('Penal', 'law'),
            ]),
        ).toEqual([
            'Constitucional e Administrativo',
            'Administrativo e Penal',
        ]);
    });

    it('never warns about unlabelled subjects', () => {
        expect(
            findAreaClashes([slot('Uma', 'other'), slot('Outra', 'other')]),
        ).toEqual([]);
    });

    it('looks past seats that are out of the rotation', () => {
        expect(
            findAreaClashes([
                slot('Constitucional', 'law'),
                slot('Português', 'languages', 'paused'),
                slot('Administrativo', 'law'),
            ]),
        ).toEqual(['Constitucional e Administrativo']);
    });

    it('has nothing to say about a rotation of one', () => {
        expect(findAreaClashes([slot('Constitucional', 'law')])).toEqual([]);
    });
});
