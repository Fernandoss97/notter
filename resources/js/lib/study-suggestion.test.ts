import { describe, expect, it } from 'vitest';
import type { SubjectSummary, TopicSummary } from '@/types/study';
import { suggestNextStudy } from './study-suggestion';

function subject(overrides: Partial<SubjectSummary> = {}): SubjectSummary {
    return {
        id: 1,
        name: 'Álgebra Linear',
        topics: [],
        ...overrides,
    };
}

function topic(overrides: Partial<TopicSummary> = {}): TopicSummary {
    return {
        id: 1,
        name: 'Matrizes',
        cycle: 0,
        position: 0,
        completed: false,
        reviewStatus: null,
        ...overrides,
    };
}

describe('suggestNextStudy', () => {
    it('suggests nothing when every topic is done', () => {
        expect(
            suggestNextStudy([
                subject({ topics: [topic({ completed: true })] }),
            ]),
        ).toBeNull();
    });

    it('leaves review urgency to the review queue', () => {
        const suggestion = suggestNextStudy([
            subject({
                id: 1,
                topics: [
                    topic({
                        id: 10,
                        position: 5,
                        completed: true,
                        reviewStatus: 'overdue',
                    }),
                ],
            }),
            subject({
                id: 2,
                topics: [topic({ id: 20, name: 'Vetores', position: 0 })],
            }),
        ]);

        // A revisão atrasada não desvia a fila aqui: quem preseleciona uma
        // revisão é a tela de foco, a partir de `dueReviews`.
        expect(suggestion).toEqual({ subjectId: 2, topicId: 20 });
    });

    it('follows the plan queue across subjects', () => {
        const suggestion = suggestNextStudy([
            subject({ id: 1, topics: [topic({ id: 10, position: 3 })] }),
            subject({
                id: 2,
                topics: [topic({ id: 20, name: 'Vetores', position: 1 })],
            }),
        ]);

        expect(suggestion).toEqual({ subjectId: 2, topicId: 20 });
    });

    it('finishes an earlier cycle before a later one, whatever the position', () => {
        const suggestion = suggestNextStudy([
            subject({
                id: 1,
                topics: [topic({ id: 10, cycle: 1, position: 2 })],
            }),
            subject({
                id: 2,
                // Acrescentada depois ao ciclo anterior: posição alta na fila,
                // mas ainda assim é o que vem antes.
                topics: [
                    topic({
                        id: 20,
                        name: 'Vetores',
                        cycle: 0,
                        position: 99,
                    }),
                ],
            }),
        ]);

        expect(suggestion).toEqual({ subjectId: 2, topicId: 20 });
    });

    it('skips completed tasks when picking the next one', () => {
        const suggestion = suggestNextStudy([
            subject({
                id: 1,
                topics: [
                    topic({ id: 10, position: 0, completed: true }),
                    topic({ id: 11, name: 'Determinantes', position: 1 }),
                ],
            }),
        ]);

        expect(suggestion).toEqual({ subjectId: 1, topicId: 11 });
    });

    it('suggests a subject with no topics at all when it is all there is', () => {
        expect(suggestNextStudy([subject({ topics: [] })])).toBeNull();
    });
});
