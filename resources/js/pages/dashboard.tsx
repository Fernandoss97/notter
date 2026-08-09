import { Head, Link } from '@inertiajs/react';
import { Page } from '@/components/page';
import { Panel } from '@/components/panel';
import { StatTile } from '@/components/stat-tile';
import { SubjectEvolutionTable } from '@/components/subject-evolution';
import { Button } from '@/components/ui/button';
import { ProgressBar } from '@/components/ui/progress-bar';
import { WeeklyBars } from '@/components/weekly-bars';
import { formatDurationMinutes } from '@/lib/format-duration';
import { dashboard } from '@/routes';
import { show as showFocus } from '@/routes/focus';
import { index as showHistory } from '@/routes/history';
import { index as showPlan } from '@/routes/plans';
import type {
    DashboardPlan,
    DashboardStats,
    RecentDay,
    SubjectEvolution,
} from '@/types/study';

interface DashboardProps {
    plan: DashboardPlan | null;
    stats: DashboardStats;
    recentDays: RecentDay[];
    evolution: SubjectEvolution | null;
}

/**
 * A tela de retrospectiva. Não repete disciplina por disciplina (isso é o
 * plano), sessão por sessão (o histórico) nem o dia de hoje (a tela de
 * foco) — fica com a semana contra a meta e a constância, que não
 * aparecem em nenhum outro lugar.
 */
export default function Dashboard({
    plan,
    stats,
    recentDays,
    evolution,
}: DashboardProps) {
    const dailyGoal =
        stats.weekGoalMinutes > 0 ? Math.round(stats.weekGoalMinutes / 7) : 0;

    return (
        <>
            <Head title="Progresso" />
            <Page>
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-extrabold tracking-tight text-foreground">
                            Progresso
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
                        <Link href={showFocus()}>Estudar agora</Link>
                    </Button>
                </div>

                {!plan ? (
                    <Panel>
                        <p className="text-sm text-fg-2">
                            Você ainda não tem um plano de estudos ativo. O
                            progresso aparece aqui assim que houver um.
                        </p>
                        <Button asChild className="w-fit">
                            <Link href={showPlan()}>
                                Criar plano de estudos
                            </Link>
                        </Button>
                    </Panel>
                ) : (
                    <>
                        <div className="stagger-children grid grid-cols-3 gap-6">
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

                        <Panel title="Últimos 7 dias">
                            <WeeklyBars
                                days={recentDays}
                                goalMinutes={dailyGoal}
                            />
                        </Panel>

                        <Panel title="Evolução por disciplina" pad={false}>
                            {evolution && evolution.rows.length > 0 ? (
                                <SubjectEvolutionTable evolution={evolution} />
                            ) : (
                                <p className="p-5 text-sm text-fg-2">
                                    Nenhuma disciplina no plano ainda. A
                                    evolução aparece aqui assim que houver
                                    tarefas para acompanhar.
                                </p>
                            )}
                        </Panel>

                        <div className="flex gap-3">
                            <Button asChild variant="secondary">
                                <Link href={showPlan()}>Ver o plano</Link>
                            </Button>
                            <Button asChild variant="secondary">
                                <Link href={showHistory()}>
                                    Ver o histórico
                                </Link>
                            </Button>
                        </div>
                    </>
                )}
            </Page>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Progresso', href: dashboard() }],
};
