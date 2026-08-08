import type { ActivityType, SubjectPriority } from '@/types/study';

export const ACTIVITY_TYPE_LABEL: Record<ActivityType, string> = {
    reading: 'Leitura',
    exercises: 'Exercícios',
    review: 'Revisão',
    practice: 'Prática',
};

export const SUBJECT_PRIORITY_LABEL: Record<SubjectPriority, string> = {
    low: 'Prioridade baixa',
    medium: 'Prioridade média',
    high: 'Prioridade alta',
};
