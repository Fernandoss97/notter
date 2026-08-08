import type { SubjectSummary, SubjectPriority } from '@/types/study';

const PRIORITY_WEIGHT: Record<SubjectPriority, number> = {
    high: 3,
    medium: 2,
    low: 1,
};

export type StudySuggestion = {
    subjectId: number;
    topicId: number | null;
};

/**
 * O que estudar agora, quando o usuário chega na tela sem ter escolhido
 * nada. Abrir dois seletores vazios transfere para ele uma decisão que os
 * dados já respondem:
 *
 * 1. revisão atrasada é sempre o mais urgente;
 * 2. depois, a disciplina de maior prioridade com tópico em aberto;
 * 3. em caso de empate, a primeira do plano (a ordem que ele definiu).
 *
 * Devolve `null` quando não há nada em aberto — aí o seletor vazio é
 * honesto.
 */
export function suggestNextStudy(
    subjects: SubjectSummary[],
): StudySuggestion | null {
    const candidates = subjects
        .map((subject) => ({
            subject,
            topic: subject.topics.find((t) => !t.completed) ?? null,
            overdue: subject.topics.find((t) => t.reviewStatus === 'overdue'),
        }))
        .filter(({ topic, overdue }) => topic !== null || overdue);

    if (candidates.length === 0) {
        return null;
    }

    const withOverdue = candidates.find(({ overdue }) => overdue);

    if (withOverdue?.overdue) {
        return {
            subjectId: withOverdue.subject.id,
            topicId: withOverdue.overdue.id,
        };
    }

    const best = candidates.reduce((current, candidate) =>
        PRIORITY_WEIGHT[candidate.subject.priority] >
        PRIORITY_WEIGHT[current.subject.priority]
            ? candidate
            : current,
    );

    return { subjectId: best.subject.id, topicId: best.topic?.id ?? null };
}
