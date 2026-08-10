export type ReviewStatus = 'overdue' | 'ontrack' | null;

export type ActivityType = 'reading' | 'exercises' | 'review' | 'practice';

export type FocusSessionState = 'running' | 'paused' | 'completed';

/**
 * O que está rodando, ortogonal a *como* está rodando: pausar durante um
 * intervalo é legítimo, por isso a fase não vive dentro do estado.
 */
export type FocusPhase = 'focus' | 'short_break' | 'long_break';

export type TopicSummary = {
    id: number;
    name: string;
    /** Onda da trilha a que a tarefa pertence. */
    cycle: number;
    /** Sequência dentro do plano — atravessa disciplinas. */
    position: number;
    completed: boolean;
    reviewStatus: ReviewStatus;
};

export type SubjectSummary = {
    id: number;
    name: string;
    topics: TopicSummary[];
};

export type StudyPlanSummary = {
    id: number;
    name: string;
    subjects: SubjectSummary[];
};

export type FocusSession = {
    id: number;
    subjectId: number | null;
    topicId: number | null;
    activityType: ActivityType | null;
    state: FocusSessionState;
    phase: FocusPhase;
    /** Relógio da fase corrente — zera a cada troca de fase. */
    elapsedSeconds: number;
    plannedSeconds: number;
    /** Tempo de foco somado da sessão inteira, sem os intervalos. */
    focusSeconds: number;
    completedCycles: number;
    notes: string;
};

export type TodayProgress = {
    minutes: number;
    goalMinutes: number;
};

export type DueReview = {
    id: number;
    subjectId: number;
    subjectName: string;
    topicId: number;
    topicName: string;
    sequence: number;
    dueOn: string;
    overdue: boolean;
};

export type PomodoroSettingSummary = {
    focusMinutes: number;
    shortBreakMinutes: number;
    longBreakMinutes: number;
    cyclesBeforeLongBreak: number;
    autoStartBreak: boolean;
    autoStartFocus: boolean;
    logSessionOnFinish: boolean;
    silentModeDuringFocus: boolean;
};

export type StudyPlanType = 'contest' | 'free';

export type PlanTopic = {
    id: number;
    name: string;
    cycle: number;
    completed: boolean;
    reviewStatus: ReviewStatus;
};

export type PlanSubject = {
    id: number;
    name: string;
    goalMinutes: number | null;
    topics: PlanTopic[];
};

export type PlanSummary = {
    id: number;
    name: string;
};

export type ActiveStudyPlan = {
    id: number;
    name: string;
    type: StudyPlanType;
    weeklyGoalMinutes: number;
    /** Data da prova no formato do input (aaaa-mm-dd), ou null. */
    examDate: string | null;
    subjects: PlanSubject[];
};

export type DashboardPlan = {
    id: number;
    name: string;
    weeklyGoalMinutes: number;
};

export type TrackPace = {
    tasksRemaining: number;
    tasksTotal: number;
    examOn: string | null;
    /** Negativo depois da prova; null quando não há data. */
    daysUntilExam: number | null;
    /** Tarefas por semana necessárias até a prova. */
    requiredPerWeek: number | null;
    /** Tarefas por semana concluídas nas últimas quatro semanas. */
    currentPerWeek: number;
    /** Data prevista de término no ritmo atual. */
    projectedOn: string | null;
    onTrack: boolean | null;
};

export type DashboardStats = {
    weekMinutes: number;
    weekGoalMinutes: number;
    topicsDone: number;
    topicsTotal: number;
    topicsDoneThisWeek: number;
    currentStreak: number;
    longestStreak: number;
};

export type RecentDay = {
    date: string;
    weekday: string;
    minutes: number;
};

export type SubjectEvolutionRow = {
    id: number;
    name: string;
    tasksDone: number;
    tasksTotal: number;
    progress: number;
    exercises: number;
    correct: number;
    /** Percentual de acerto, ou null quando não há questão resolvida. */
    accuracy: number | null;
};

export type SubjectEvolution = {
    rows: SubjectEvolutionRow[];
    totals: Omit<SubjectEvolutionRow, 'id' | 'name'>;
};

export type RecentSession = {
    date: string;
    subject: string;
    durationMinutes: number;
    activityType: ActivityType;
    exercises: number | null;
    accuracy: number | null;
};

export type HistoryPeriod = '7' | '30' | 'all';

export type HistoryTypeFilter = ActivityType | 'all';

export type HistoryFilters = {
    period: HistoryPeriod;
    type: HistoryTypeFilter;
};

export type TrackTask = {
    id: number;
    /** Nº da tarefa na fila do plano inteiro, derivado da ordem. */
    number: number;
    name: string;
    subjectName: string;
    completed: boolean;
    /** Data em que foi concluída, no formato dd/mm/aaaa. */
    completedOn: string | null;
    reviewStatus: ReviewStatus;
    exercisesTotal: number;
    exercisesCorrect: number;
    /** Percentual de acerto, ou null quando não há questão registrada. */
    accuracy: number | null;
};

export type TrackCycle = {
    cycle: number;
    done: number;
    total: number;
    exercises: number;
    correct: number;
    accuracy: number | null;
    tasks: TrackTask[];
};

export type TrackPlan = {
    id: number;
    name: string;
    subjects: { id: number; name: string }[];
};

/**
 * "Fora do rodízio", não "apagada": a cadeira pausada continua na tela
 * justamente para poder voltar — é assim que o ciclo é enxugado para um
 * nivelamento sem perder o que já estava montado.
 */
export type CycleSlotState = 'active' | 'paused';

/**
 * A execução de uma cadeira do rodízio numa volta. Não é um pomodoro: o
 * bloco acumula tempo de foco ao longo de vários pomodoros e vários dias,
 * e é isso que faz o "retomar de onde parou" funcionar.
 */
export type CycleBlockSummary = {
    id: number;
    subjectId: number;
    subjectName: string;
    lap: number;
    targetMinutes: number;
    /** Já inclui o segmento vivo da sessão que alimenta este bloco. */
    elapsedSeconds: number;
    remainingMinutes: number;
    progressPercent: number;
    completed: boolean;
    /** Há uma sessão de foco em andamento alimentando este bloco. */
    isActive: boolean;
};

/**
 * Em que pé está a disciplina: primeiro contato com a teoria, já em
 * revisão, ou encerrada. Sair da leitura reduz o que ela ocupa no rodízio,
 * e é essa folga que abre espaço para uma matéria nova.
 */
export type SubjectMode = 'reading' | 'review' | 'done';

export type CycleSlotSummary = {
    id: number;
    subjectId: number;
    subjectName: string;
    subjectMode: SubjectMode;
    blockMinutes: number;
    /** Ainda dentro da janela de imersão inicial de uma matéria nova. */
    boosted: boolean;
    state: CycleSlotState;
    /** É a vez desta disciplina na volta corrente. */
    isCurrent: boolean;
    block: CycleBlockSummary | null;
};

export type StudyCycle = {
    lap: number;
    currentBlockId: number | null;
    slots: CycleSlotSummary[];
    /** Matérias em primeiro contato hoje, contra o que se recomenda. */
    readingCount: number;
    recommendedReading: number;
};

export type PomodoroSettingFull = {
    focusMinutes: number;
    shortBreakMinutes: number;
    longBreakMinutes: number;
    cyclesBeforeLongBreak: number;
    autoStartBreak: boolean;
    autoStartFocus: boolean;
    logSessionOnFinish: boolean;
    silentModeDuringFocus: boolean;
    excludeBreaksFromStudyTime: boolean;
};
