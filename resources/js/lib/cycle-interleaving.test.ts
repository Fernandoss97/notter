import { describe, expect, it } from 'vitest';
import type { CycleLapPassage, SubjectArea } from '@/types/study';
import { findAreaClashes } from './cycle-interleaving';

let nextSlotId = 1;

function passage(
    name: string,
    area: SubjectArea,
    overrides: Partial<CycleLapPassage> = {},
): CycleLapPassage {
    return {
        slotId: nextSlotId++,
        sequence: 1,
        subjectName: name,
        subjectArea: area,
        ...overrides,
    };
}

describe('findAreaClashes', () => {
    it('finds two subjects of the same family in a row', () => {
        expect(
            findAreaClashes([
                passage('Constitucional', 'law'),
                passage('Administrativo', 'law'),
            ]),
        ).toEqual(['Constitucional e Administrativo']);
    });

    it('says nothing when the families alternate', () => {
        expect(
            findAreaClashes([
                passage('Constitucional', 'law'),
                passage('Português', 'languages'),
                passage('Administrativo', 'law'),
            ]),
        ).toEqual([]);
    });

    it('reports every pair in a run of three', () => {
        expect(
            findAreaClashes([
                passage('Constitucional', 'law'),
                passage('Administrativo', 'law'),
                passage('Penal', 'law'),
            ]),
        ).toEqual([
            'Constitucional e Administrativo',
            'Administrativo e Penal',
        ]);
    });

    it('never warns about unlabelled subjects', () => {
        expect(
            findAreaClashes([
                passage('Uma', 'other'),
                passage('Outra', 'other'),
            ]),
        ).toEqual([]);
    });

    it('has nothing to say about a rotation of one', () => {
        expect(findAreaClashes([passage('Constitucional', 'law')])).toEqual([]);
    });

    // O peso fazendo o que foi pedido não é conselho: "Direito e Direito
    // são da mesma área" seria a tela falando sozinha.
    it('does not warn about a subject sitting next to itself', () => {
        expect(
            findAreaClashes([
                { ...passage('Direito', 'law'), slotId: 7, sequence: 1 },
                { ...passage('Direito', 'law'), slotId: 7, sequence: 2 },
            ]),
        ).toEqual([]);
    });

    it('still warns about the neighbour a repeated passage brought close', () => {
        expect(
            findAreaClashes([
                { ...passage('Direito', 'law'), slotId: 7, sequence: 1 },
                passage('Português', 'languages'),
                { ...passage('Direito', 'law'), slotId: 7, sequence: 2 },
                passage('Administrativo', 'law'),
            ]),
        ).toEqual(['Direito e Administrativo']);
    });
});
