import { Head, Link, usePage } from '@inertiajs/react';
import { Page } from '@/components/page';
import { Panel } from '@/components/panel';
import { SessionsTable } from '@/components/sessions-table';
import type { SessionRow } from '@/components/sessions-table';
import { StatTile } from '@/components/stat-tile';
import { SubjectCard } from '@/components/subject-card';
import { Button } from '@/components/ui/button';
import { ProgressBar } from '@/components/ui/progress-bar';
import { formatDurationMinutes } from '@/lib/format-duration';
import { ACTIVITY_TYPE_LABEL } from '@/lib/study-labels';
import { dashboard } from '@/routes';
import { show as showFocus } from '@/routes/focus';
import { index as showPlan } from '@/routes/plans';
import type {
    DashboardPlan,
    DashboardStats,
    DashboardSubject,
    RecentSession,
} from '@/types/study';

interface DashboardProps {
    plan: DashboardPlan | null;
    stats: DashboardStats;
    subjects: DashboardSubject[];
    recentSessions: RecentSession[];
}

export default function Dashboard({
    plan,
    stats,
    subjects,
    recentSessions,
}: DashboardProps) {
    const { auth } = usePage().props;

    const sessionRows: SessionRow[] = recentSessions.map((session) => ({
        date: session.date,
        subject: session.subject,
        duration: formatDurationMinutes(session.durationMinutes),
        type: ACTIVITY_TYPE_LABEL[session.activityType],
        exercises: session.exercises,
        accuracy: session.accuracy,
    }));

    return (
        <>
            <Head title="Dashboard" />
            <Page>
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-extrabold tracking-tight text-foreground">
                            Olá, {auth.user.name}
                        </h1>
                        {plan && (
                            <p className="mt-0.5 text-base text-fg-2">
                                {plan.name} · meta de{' '}
                                {Math.round(plan.weeklyGoalMinutes / 60)}h por
                                semana
                            </p>
                        )}
                    </div>
                    <Button asChild>
                        <Link href={showFocus()}>Iniciar sessão de foco</Link>
                    </Button>
                </div>

                {!plan ? (
                    <Panel>
                        <p className="text-sm text-fg-2">
                            Você ainda não tem um plano de estudos ativo.
                        </p>
                        <Button asChild className="w-fit">
                            <Link href={showPlan()}>
                                Criar plano de estudos
                            </Link>
                        </Button>
                    </Panel>
                ) : (
                    <>
                        <div className="grid grid-cols-4 gap-6">
                            <StatTile
                                label="Horas hoje"
                                value={formatDurationMinutes(
                                    stats.todayMinutes,
                                )}
                                hint={`meta: ${formatDurationMinutes(stats.todayGoalMinutes)}`}
                            >
                                <ProgressBar
                                    value={
                                        stats.todayGoalMinutes > 0
                                            ? Math.round(
                                                  (stats.todayMinutes /
                                                      stats.todayGoalMinutes) *
                                                      100,
                                              )
                                            : 0
                                    }
                                    color="focus"
                                />
                            </StatTile>
                            <StatTile
                                label="Semana"
                                value={formatDurationMinutes(stats.weekMinutes)}
                                hint={`meta: ${formatDurationMinutes(stats.weekGoalMinutes)}`}
                            >
                                <ProgressBar
                                    value={
                                        stats.weekGoalMinutes > 0
                                            ? Math.round(
                                                  (stats.weekMinutes /
                                                      stats.weekGoalMinutes) *
                                                      100,
                                              )
                                            : 0
                                    }
                                    color="info"
                                />
                            </StatTile>
                            <StatTile
                                label="Tópicos concluídos"
                                value={stats.topicsDone}
                                unit={`/${stats.topicsTotal}`}
                                hint={`+${stats.topicsDoneThisWeek} nesta semana`}
                            >
                                <ProgressBar
                                    value={
                                        stats.topicsTotal > 0
                                            ? Math.round(
                                                  (stats.topicsDone /
                                                      stats.topicsTotal) *
                                                      100,
                                              )
                                            : 0
                                    }
                                    color="success"
                                />
                            </StatTile>
                            <StatTile
                                label="Sequência"
                                value={stats.currentStreak}
                                unit=" dias"
                                color="focus"
                                hint={`maior sequência: ${stats.longestStreak}`}
                            />
                        </div>

                        {subjects.length > 0 && (
                            <Panel title="Disciplinas do plano">
                                <div className="grid grid-cols-2 gap-4">
                                    {subjects.map((subject) => (
                                        <SubjectCard
                                            key={subject.id}
                                            subject={subject.name}
                                            topicsDone={subject.topicsDone}
                                            topicsTotal={subject.topicsTotal}
                                            progress={subject.progress}
                                            reviewStatus={subject.reviewStatus}
                                        />
                                    ))}
                                </div>
                            </Panel>
                        )}

                        {sessionRows.length > 0 && (
                            <Panel title="Histórico de sessões" pad={false}>
                                <SessionsTable rows={sessionRows} />
                            </Panel>
                        )}
                    </>
                )}
            </Page>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
