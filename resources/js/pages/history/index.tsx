import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { Panel } from '@/components/panel';
import { SessionsTable } from '@/components/sessions-table';
import type { SessionRow } from '@/components/sessions-table';
import { SegmentedControl } from '@/components/ui/segmented-control';
import { formatDurationMinutes } from '@/lib/format-duration';
import { ACTIVITY_TYPE_LABEL } from '@/lib/study-labels';
import { cn } from '@/lib/utils';
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

    // O `value` dos controles vem do servidor; sem estado pendente o clique
    // só "acontece" quando a resposta chega. Aqui a seleção move na hora e a
    // tabela apenas esmaece enquanto os dados novos não chegam.
    const [pendingFilters, setPendingFilters] =
        useState<Partial<HistoryFilters> | null>(null);
    const activeFilters = { ...filters, ...pendingFilters };

    function updateFilters(next: Partial<HistoryFilters>) {
        const target = { ...activeFilters, ...next };

        setPendingFilters((current) => ({ ...current, ...next }));

        router.get(index().url, target, {
            preserveState: true,
            preserveScroll: true,
            showProgress: false,
            onFinish: () => setPendingFilters(null),
        });
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
                        value={activeFilters.period}
                        onValueChange={(value) =>
                            updateFilters({ period: value as HistoryPeriod })
                        }
                    />
                    <SegmentedControl
                        options={TYPE_OPTIONS}
                        value={activeFilters.type}
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
                    <div
                        aria-busy={pendingFilters !== null}
                        className={cn(
                            'transition-opacity duration-fast ease-out',
                            pendingFilters !== null && 'opacity-50',
                        )}
                    >
                        {rows.length > 0 ? (
                            <SessionsTable rows={rows} />
                        ) : (
                            <p className="px-5 py-8 text-center text-sm text-muted-foreground">
                                Nenhuma sessão registrada neste período.
                            </p>
                        )}
                    </div>
                </Panel>
            </Page>
        </>
    );
}

HistoryIndex.layout = {
    breadcrumbs: [{ title: 'Histórico', href: index() }],
};
