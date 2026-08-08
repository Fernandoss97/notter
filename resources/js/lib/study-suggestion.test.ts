import { describe, expect, it } from 'vitest';
import type { SubjectSummary } from '@/types/study';
import { suggestNextStudy } from './study-suggestion';

function subject(overrides: Partial<SubjectSummary> = {}): SubjectSummary {
    return {
        id: 1,
        name: 'Álgebra Linear',
        priority: 'medium',
        topics: [],
        ...overrides,
    };
}

describe('suggestNextStudy', () => {
    it('suggests nothing when every topic is done', () => {
        expect(
            suggestNextStudy([
                subject({
                    topics: [
                        {
                            id: 1,
                            name: 'Matrizes',
                            completed: true,
                            reviewStatus: null,
                        },
                    ],
                }),
            ]),
        ).toBeNull();
    });

    it('puts an overdue review ahead of a higher priority subject', () => {
        const suggestion = suggestNextStudy([
            subject({
                id: 1,
                priority: 'low',
                topics: [
                    {
                        id: 10,
                        name: 'Matrizes',
                        completed: true,
                        reviewStatus: 'overdue',
                    },
                ],
            }),
            subject({
                id: 2,
                priority: 'high',
                topics: [
                    {
                        id: 20,
                        name: 'Vetores',
                        completed: false,
                        reviewStatus: null,
                    },
                ],
            }),
        ]);

        expect(suggestion).toEqual({ subjectId: 1, topicId: 10 });
    });

    it('falls back to the highest priority subject with an open topic', () => {
        const suggestion = suggestNextStudy([
            subject({
                id: 1,
                priority: 'low',
                topics: [
                    {
                        id: 10,
                        name: 'Matrizes',
                        completed: false,
                        reviewStatus: null,
                    },
                ],
            }),
            subject({
                id: 2,
                priority: 'high',
                topics: [
                    {
                        id: 20,
                        name: 'Vetores',
                        completed: false,
                        reviewStatus: null,
                    },
                ],
            }),
        ]);

        expect(suggestion).toEqual({ subjectId: 2, topicId: 20 });
    });

    it('keeps the plan order when priorities tie', () => {
        const suggestion = suggestNextStudy([
            subject({
                id: 1,
                topics: [
                    {
                        id: 10,
                        name: 'Matrizes',
                        completed: false,
                        reviewStatus: null,
                    },
                ],
            }),
            subject({
                id: 2,
                topics: [
                    {
                        id: 20,
                        name: 'Vetores',
                        completed: false,
                        reviewStatus: null,
                    },
                ],
            }),
        ]);

        expect(suggestion).toEqual({ subjectId: 1, topicId: 10 });
    });

    it('suggests a subject with no topics at all when it is all there is', () => {
        expect(suggestNextStudy([subject({ topics: [] })])).toBeNull();
    });
});
