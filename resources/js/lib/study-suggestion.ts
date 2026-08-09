import type { SubjectSummary, TopicSummary } from '@/types/study';

export type StudySuggestion = {
    subjectId: number;
    topicId: number | null;
};

/**
 * O que estudar agora, quando o usuário chega na tela sem ter escolhido
 * nada: a próxima tarefa aberta da trilha — a fila que ele mesmo montou,
 * ciclo a ciclo, atravessando disciplinas.
 *
 * Revisão devida não entra aqui. Quem responde por urgência de revisão é a
 * fila ao lado (`dueReviews`), ordenada por vencimento; duplicar esse
 * critério com outra régua faria a preseleção discordar da primeira linha
 * da fila na mesma tela.
 *
 * Devolve `null` quando não há nada em aberto — aí o seletor vazio é
 * honesto.
 */
export function suggestNextStudy(
    subjects: SubjectSummary[],
): StudySuggestion | null {
    const next = subjects
        .flatMap((subject) =>
            subject.topics
                .filter((topic) => !topic.completed)
                .map((topic) => ({ subject, topic })),
        )
        .sort((a, b) => compareQueue(a.topic, b.topic))[0];

    if (!next) {
        return null;
    }

    return { subjectId: next.subject.id, topicId: next.topic.id };
}

/**
 * A ordem da trilha: ciclo antes de posição. `position` já é a sequência
 * dentro do plano, então ela sozinha resolveria — o ciclo vem primeiro
 * porque uma tarefa acrescentada depois a um ciclo anterior recebe uma
 * posição alta e ainda assim precisa ser estudada antes.
 */
function compareQueue(a: TopicSummary, b: TopicSummary): number {
    return a.cycle - b.cycle || a.position - b.position;
}
