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
