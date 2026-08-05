export type ReviewStatus = 'overdue' | 'ontrack' | null;

export type ActivityType = 'reading' | 'exercises' | 'review' | 'practice';

export type FocusSessionState = 'running' | 'paused' | 'completed';

export type TopicSummary = {
    id: number;
    name: string;
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
    elapsedSeconds: number;
    plannedSeconds: number;
};

export type PomodoroSettingSummary = {
    focusMinutes: number;
    shortBreakMinutes: number;
    longBreakMinutes: number;
    cyclesBeforeLongBreak: number;
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
