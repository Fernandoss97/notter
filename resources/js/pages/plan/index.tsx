import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { Panel } from '@/components/panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ProgressBar } from '@/components/ui/progress-bar';
import { ReviewStatusBadge } from '@/components/ui/review-status-badge';
import { SegmentedControl } from '@/components/ui/segmented-control';
import { cn } from '@/lib/utils';
import { show as showFocus } from '@/routes/focus';
import { activate, index, store as storePlan } from '@/routes/plans';
import { store as storeSubject } from '@/routes/subjects';
import {
    complete as completeTopic,
    store as storeTopic,
} from '@/routes/topics';
import type {
    ActiveStudyPlan,
    PlanSubject,
    PlanSummary,
    StudyPlanType,
    SubjectPriority,
} from '@/types/study';

const TYPE_OPTIONS: { value: StudyPlanType; label: string }[] = [
    { value: 'contest', label: 'Concurso' },
    { value: 'free', label: 'Livre' },
];

const PRIORITY_OPTIONS: { value: SubjectPriority; label: string }[] = [
    { value: 'low', label: 'Baixa' },
    { value: 'medium', label: 'Média' },
    { value: 'high', label: 'Alta' },
];

const PRIORITY_LABEL: Record<SubjectPriority, string> = {
    low: 'Prioridade baixa',
    medium: 'Prioridade média',
    high: 'Prioridade alta',
};

interface PlanIndexProps {
    plans: PlanSummary[];
    activePlan: ActiveStudyPlan | null;
}

export default function PlanIndex({ plans, activePlan }: PlanIndexProps) {
    return (
        <>
            <Head title="Plano de estudos" />
            <Page>
                {!activePlan ? (
                    <CreatePlanPanel />
                ) : (
                    <ActivePlanView plans={plans} plan={activePlan} />
                )}
            </Page>
        </>
    );
}

PlanIndex.layout = {
    breadcrumbs: [{ title: 'Plano de estudos', href: index() }],
};

function CreatePlanPanel() {
    const form = useForm({
        name: '',
        type: 'free' as StudyPlanType,
        weekly_goal_minutes: 20 * 60,
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(storePlan().url);
    }

    return (
        <Panel title="Criar plano de estudos">
            <form onSubmit={submit} className="flex flex-col gap-4">
                <div className="flex flex-col gap-2">
                    <Label htmlFor="plan_name">Nome do plano</Label>
                    <Input
                        id="plan_name"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        placeholder="Concurso XYZ 2026"
                    />
                    {form.errors.name && (
                        <p className="text-sm text-destructive">
                            {form.errors.name}
                        </p>
                    )}
                </div>

                <div className="flex flex-col gap-2">
                    <Label>Tipo do plano</Label>
                    <SegmentedControl
                        options={TYPE_OPTIONS}
                        value={form.data.type}
                        onValueChange={(value) =>
                            form.setData('type', value as StudyPlanType)
                        }
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="plan_weekly_goal">
                        Meta semanal (horas)
                    </Label>
                    <Input
                        id="plan_weekly_goal"
                        type="number"
                        min={1}
                        value={form.data.weekly_goal_minutes / 60}
                        onChange={(event) =>
                            form.setData(
                                'weekly_goal_minutes',
                                Number(event.target.value || 0) * 60,
                            )
                        }
                    />
                    {form.errors.weekly_goal_minutes && (
                        <p className="text-sm text-destructive">
                            {form.errors.weekly_goal_minutes}
                        </p>
                    )}
                </div>

                <Button
                    type="submit"
                    disabled={form.processing}
                    className="w-fit"
                >
                    Criar plano
                </Button>
            </form>
        </Panel>
    );
}

function ActivePlanView({
    plans,
    plan,
}: {
    plans: PlanSummary[];
    plan: ActiveStudyPlan;
}) {
    const topicsTotal = plan.subjects.reduce(
        (total, subject) => total + subject.topics.length,
        0,
    );

    return (
        <>
            <PageHeader
                title={plan.name}
                subtitle={`${plan.type === 'contest' ? 'Concurso' : 'Estudo livre'} · ${plan.subjects.length} disciplinas · ${topicsTotal} tópicos`}
                actions={
                    <Button asChild>
                        <Link href={showFocus()}>Estudar agora</Link>
                    </Button>
                }
            />

            {plans.length > 1 && (
                <div className="flex items-center gap-3">
                    <span className="text-xs text-muted-foreground">
                        Plano ativo
                    </span>
                    <SegmentedControl
                        options={plans.map((p) => ({
                            value: String(p.id),
                            label: p.name,
                        }))}
                        value={String(plan.id)}
                        onValueChange={(value) =>
                            router.post(
                                activate(Number(value)).url,
                                {},
                                { preserveScroll: true },
                            )
                        }
                    />
                </div>
            )}

            <AddSubjectPanel />

            <div className="flex flex-col gap-3">
                {plan.subjects.map((subject) => (
                    <SubjectRow key={subject.id} subject={subject} />
                ))}
            </div>
        </>
    );
}

function AddSubjectPanel() {
    const form = useForm({
        name: '',
        priority: 'medium' as SubjectPriority,
        goal_minutes: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.transform((data) => ({
            name: data.name,
            priority: data.priority,
            goal_minutes: data.goal_minutes
                ? Number(data.goal_minutes) * 60
                : null,
        }));

        form.post(storeSubject().url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <Panel title="Adicionar disciplina">
            <form onSubmit={submit} className="flex flex-wrap items-end gap-4">
                <div className="flex min-w-[200px] flex-1 flex-col gap-2">
                    <Label htmlFor="subject_name">Nome</Label>
                    <Input
                        id="subject_name"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        placeholder="Álgebra Linear"
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label>Prioridade</Label>
                    <SegmentedControl
                        options={PRIORITY_OPTIONS}
                        value={form.data.priority}
                        onValueChange={(value) =>
                            form.setData('priority', value as SubjectPriority)
                        }
                    />
                </div>

                <div className="flex w-32 flex-col gap-2">
                    <Label htmlFor="subject_goal">Meta (horas)</Label>
                    <Input
                        id="subject_goal"
                        type="number"
                        min={1}
                        value={form.data.goal_minutes}
                        onChange={(event) =>
                            form.setData('goal_minutes', event.target.value)
                        }
                    />
                    {form.errors.goal_minutes && (
                        <p className="text-sm text-destructive">
                            {form.errors.goal_minutes}
                        </p>
                    )}
                </div>

                <Button type="submit" disabled={form.processing}>
                    Adicionar
                </Button>
            </form>
        </Panel>
    );
}

function SubjectRow({ subject }: { subject: PlanSubject }) {
    const topicsDone = subject.topics.filter((t) => t.completed).length;
    const progress =
        subject.topics.length > 0
            ? Math.round((topicsDone / subject.topics.length) * 100)
            : 0;

    return (
        <Panel>
            <div className="flex items-center justify-between gap-4">
                <div>
                    <div className="text-md font-bold text-foreground">
                        {subject.name}
                    </div>
                    <div className="mt-0.5 text-xs text-muted-foreground">
                        {subject.topics.length} tópicos ·{' '}
                        {PRIORITY_LABEL[subject.priority]}
                        {subject.goalMinutes &&
                            ` · meta ${Math.round(subject.goalMinutes / 60)}h/semana`}
                    </div>
                </div>
                <div className="w-40">
                    <ProgressBar value={progress} showLabel />
                </div>
            </div>

            <div className="flex flex-col gap-1.5">
                {subject.topics.map((topic) => (
                    <TopicRow key={topic.id} topic={topic} />
                ))}
            </div>

            <AddTopicForm subjectId={subject.id} />
        </Panel>
    );
}

function TopicRow({ topic }: { topic: PlanSubject['topics'][number] }) {
    function markComplete() {
        router.post(completeTopic(topic.id).url, {}, { preserveScroll: true });
    }

    return (
        <div className="flex items-center gap-3 rounded-sm px-2 py-1.5 hover:bg-muted">
            <button
                type="button"
                onClick={topic.completed ? undefined : markComplete}
                disabled={topic.completed}
                aria-label={
                    topic.completed
                        ? `${topic.name} concluído`
                        : `Marcar ${topic.name} como concluído`
                }
                className={cn(
                    'flex size-4 shrink-0 items-center justify-center rounded-[4px] border text-[11px]',
                    topic.completed
                        ? 'border-transparent bg-status-success text-white'
                        : 'border-border-strong bg-transparent',
                )}
            >
                {topic.completed && '✓'}
            </button>
            <span
                className={cn(
                    'flex-1 text-sm',
                    topic.completed ? 'text-fg-2' : 'text-foreground',
                )}
            >
                {topic.name}
            </span>
            {topic.reviewStatus && (
                <ReviewStatusBadge status={topic.reviewStatus} />
            )}
        </div>
    );
}

function AddTopicForm({ subjectId }: { subjectId: number }) {
    const form = useForm({ name: '' });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.transform((data) => ({ ...data, subject_id: subjectId }));

        form.post(storeTopic().url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <form onSubmit={submit} className="flex items-center gap-2 pt-1">
            <Input
                value={form.data.name}
                onChange={(event) => form.setData('name', event.target.value)}
                placeholder="Novo tópico"
                className="h-8"
            />
            <Button
                type="submit"
                variant="secondary"
                size="sm"
                disabled={form.processing}
            >
                Adicionar tópico
            </Button>
        </form>
    );
}
