import type { SubjectSummary, TopicSummary } from '@/types/study';

export type StudySuggestion = {
    subjectId: number;
    topicId: number | null;
};

/**
 * O que estudar agora, quando o usuário chega na tela sem ter escolhido
 * nada: a próxima tarefa aberta da trilha — a fila que ele mesmo montou,
 * onda a onda, atravessando disciplinas.
 *
 * Com `onlySubjectId`, a busca fica presa a uma disciplina. É assim que o
 * ciclo de estudos e a trilha se compõem: o rodízio diz de quem é a vez, a
 * trilha diz qual tarefa daquela disciplina vem agora. A disciplina volta
 * mesmo sem tarefa aberta (`topicId: null`) — é a vez dela de qualquer
 * jeito, e mudar de disciplina aqui faria a sessão deixar de creditar o
 * bloco.
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
    onlySubjectId?: number | null,
): StudySuggestion | null {
    const pool =
        onlySubjectId != null
            ? subjects.filter((subject) => subject.id === onlySubjectId)
            : subjects;

    const next = pool
        .flatMap((subject) =>
            subject.topics
                .filter((topic) => !topic.completed)
                .map((topic) => ({ subject, topic })),
        )
        .sort((a, b) => compareQueue(a.topic, b.topic))[0];

    if (!next) {
        return onlySubjectId != null && pool.length > 0
            ? { subjectId: onlySubjectId, topicId: null }
            : null;
    }

    return { subjectId: next.subject.id, topicId: next.topic.id };
}

/**
 * A ordem da trilha: onda antes de posição. `position` já é a sequência
 * dentro do plano, então ela sozinha resolveria — a onda vem primeiro
 * porque uma tarefa acrescentada depois a uma onda anterior recebe uma
 * posição alta e ainda assim precisa ser estudada antes.
 */
function compareQueue(a: TopicSummary, b: TopicSummary): number {
    return a.cycle - b.cycle || a.position - b.position;
}
