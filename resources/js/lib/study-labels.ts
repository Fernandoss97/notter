import type { ActivityType, SubjectMode } from '@/types/study';

export const ACTIVITY_TYPE_LABEL: Record<ActivityType, string> = {
    reading: 'Leitura',
    exercises: 'Exercícios',
    review: 'Revisão',
    practice: 'Prática',
};

/**
 * O pé em que a disciplina está. "Encerrada" e não "concluída": o que
 * acabou é a passagem dela pelo rodízio, não o estudo — ela continua no
 * plano, com trilha e revisões, e pode voltar.
 */
export const SUBJECT_MODE_LABEL: Record<SubjectMode, string> = {
    reading: 'Leitura',
    review: 'Revisão',
    done: 'Encerrada',
};
