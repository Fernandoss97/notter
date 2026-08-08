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
    completed: boolean;
    reviewStatus: ReviewStatus;
};

export type SubjectSummary = {
    id: number;
    name: string;
    priority: SubjectPriority;
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

export type SubjectPriority = 'low' | 'medium' | 'high';

export type StudyPlanType = 'contest' | 'free';

export type PlanTopic = {
    id: number;
    name: string;
    completed: boolean;
    reviewStatus: ReviewStatus;
};

export type PlanSubject = {
    id: number;
    name: string;
    priority: SubjectPriority;
    goalMinutes: number | null;
    topics: PlanTopic[];
};

export type PlanSummary = {
    id: number;
    name: string;
    isActive: boolean;
};

export type ActiveStudyPlan = {
    id: number;
    name: string;
    type: StudyPlanType;
    weeklyGoalMinutes: number;
    subjects: PlanSubject[];
};

export type DashboardPlan = {
    id: number;
    name: string;
    weeklyGoalMinutes: number;
};

export type DashboardStats = {
    todayMinutes: number;
    todayGoalMinutes: number;
    weekMinutes: number;
    weekGoalMinutes: number;
    topicsDone: number;
    topicsTotal: number;
    topicsDoneThisWeek: number;
    currentStreak: number;
    longestStreak: number;
};

export type DashboardSubject = {
    id: number;
    name: string;
    topicsDone: number;
    topicsTotal: number;
    progress: number;
    reviewStatus: ReviewStatus;
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
