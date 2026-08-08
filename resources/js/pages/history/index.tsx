import { Head, router } from '@inertiajs/react';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { Panel } from '@/components/panel';
import { SessionsTable } from '@/components/sessions-table';
import type { SessionRow } from '@/components/sessions-table';
import { SegmentedControl } from '@/components/ui/segmented-control';
import { formatDurationMinutes } from '@/lib/format-duration';
import { ACTIVITY_TYPE_LABEL } from '@/lib/study-labels';
import { index } from '@/routes/history';
import type {
    HistoryFilters,
    HistoryPeriod,
    HistoryTypeFilter,
    RecentSession,
} from '@/types/study';

const PERIOD_OPTIONS: { value: HistoryPeriod; label: string }[] = [
    { value: '7', label: '7 dias' },
    { value: '30', label: '30 dias' },
    { value: 'all', label: 'Tudo' },
];

const TYPE_OPTIONS: { value: HistoryTypeFilter; label: string }[] = [
    { value: 'all', label: 'Todos os tipos' },
    ...(
        Object.entries(ACTIVITY_TYPE_LABEL) as [HistoryTypeFilter, string][]
    ).map(([value, label]) => ({ value, label })),
];

interface HistoryIndexProps {
    filters: HistoryFilters;
    total: number;
    sessions: RecentSession[];
}

export default function HistoryIndex({
    filters,
    total,
    sessions,
}: HistoryIndexProps) {
    const rows: SessionRow[] = sessions.map((session) => ({
        date: session.date,
        subject: session.subject,
        duration: formatDurationMinutes(session.durationMinutes),
        type: ACTIVITY_TYPE_LABEL[session.activityType],
        exercises: session.exercises,
        accuracy: session.accuracy,
    }));

    function updateFilters(next: Partial<HistoryFilters>) {
        router.get(
            index().url,
            { ...filters, ...next },
            { preserveState: true, preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Histórico" />
            <Page>
                <PageHeader
                    title="Histórico de sessões"
                    subtitle={`${total} sessões registradas`}
                />

                <div className="flex flex-wrap items-center gap-4">
                    <SegmentedControl
                        options={PERIOD_OPTIONS}
                        value={filters.period}
                        onValueChange={(value) =>
                            updateFilters({ period: value as HistoryPeriod })
                        }
                    />
                    <SegmentedControl
                        options={TYPE_OPTIONS}
                        value={filters.type}
                        onValueChange={(value) =>
                            updateFilters({
                                type: value as HistoryTypeFilter,
                            })
                        }
                    />
                </div>

                <Panel
                    title="Sessões"
                    pad={false}
                    action={
                        <span className="font-mono text-xs text-muted-foreground">
                            {sessions.length} de {total}
                        </span>
                    }
                >
                    <SessionsTable rows={rows} />
                </Panel>
            </Page>
        </>
    );
}

HistoryIndex.layout = {
    breadcrumbs: [{ title: 'Histórico', href: index() }],
};
