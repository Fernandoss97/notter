import { Head, Link, router, useForm } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { Panel } from '@/components/panel';
import { PlanSwitcher } from '@/components/plan-switcher';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ProgressBar } from '@/components/ui/progress-bar';
import { ReviewStatusBadge } from '@/components/ui/review-status-badge';
import { SegmentedControl } from '@/components/ui/segmented-control';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { show as showFocus } from '@/routes/focus';
import {
    index,
    store as storePlan,
    update as updatePlan,
} from '@/routes/plans';
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
} from '@/types/study';

const TYPE_OPTIONS: { value: StudyPlanType; label: string }[] = [
    { value: 'contest', label: 'Concurso' },
    { value: 'free', label: 'Livre' },
];

type PlanFormData = {
    name: string;
    type: StudyPlanType;
    weekly_goal_minutes: number;
    exam_date: string;
};

const EMPTY_PLAN: PlanFormData = {
    name: '',
    type: 'free',
    weekly_goal_minutes: 20 * 60,
    exam_date: '',
};

interface PlanIndexProps {
    plans: PlanSummary[];
    activePlan: ActiveStudyPlan | null;
}

export default function PlanIndex({ plans, activePlan }: PlanIndexProps) {
    return (
        <>
            <Head title="Plano de estudos" />
            <Page maxWidth="narrow">
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

/**
 * Os três campos de um plano, compartilhados pelo painel de criação, pelo
 * diálogo de novo plano e pelo de edição. `idPrefix` mantém os `id` únicos
 * quando dois desses formulários estão montados ao mesmo tempo.
 */
function PlanFields({
    form,
    idPrefix,
    nameFieldClassName,
}: {
    form: InertiaFormProps<PlanFormData>;
    idPrefix: string;
    nameFieldClassName?: string;
}) {
    return (
        <>
            <div className={cn('flex flex-col gap-2', nameFieldClassName)}>
                <Label htmlFor={`${idPrefix}_name`}>Nome do plano</Label>
                <Input
                    id={`${idPrefix}_name`}
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
                <Label htmlFor={`${idPrefix}_weekly_goal`}>
                    Meta semanal (horas)
                </Label>
                <Input
                    id={`${idPrefix}_weekly_goal`}
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

            <div className="flex flex-col gap-2">
                <Label htmlFor={`${idPrefix}_exam_date`}>
                    Data da prova (opcional)
                </Label>
                <Input
                    id={`${idPrefix}_exam_date`}
                    name="exam_date"
                    type="date"
                    className="w-fit"
                    value={form.data.exam_date}
                    onChange={(event) =>
                        form.setData('exam_date', event.target.value)
                    }
                />
                {form.errors.exam_date && (
                    <p className="text-sm text-destructive">
                        {form.errors.exam_date}
                    </p>
                )}
            </div>
        </>
    );
}

function CreatePlanPanel() {
    const form = useForm<PlanFormData>({ ...EMPTY_PLAN });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(storePlan().url);
    }

    return (
        <Panel title="Criar plano de estudos">
            <form onSubmit={submit} className="flex flex-col gap-4">
                <PlanFields
                    form={form}
                    idPrefix="plan"
                    nameFieldClassName="max-w-sm"
                />

                <Button
                    type="submit"
                    disabled={form.processing}
                    className="w-fit"
                >
                    {form.processing && <Spinner />}
                    Criar plano
                </Button>
            </form>
        </Panel>
    );
}

/**
 * Mesmo formulário do painel, disponível quando já existe um plano ativo —
 * sem isso o primeiro plano criado seria o único possível.
 */
function CreatePlanDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm<PlanFormData>({ ...EMPTY_PLAN });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(storePlan().url, {
            preserveScroll: true,
            // O visit recarrega a mesma página, então o diálogo sobrevive ao
            // sucesso: precisa ser fechado na mão.
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                form.clearErrors();
                // Cada abertura começa em branco — inclusive depois de um
                // cancelamento, como no diálogo de edição.
                form.reset();
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline">Novo plano</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Novo plano</DialogTitle>
                    <DialogDescription>
                        O novo plano passa a ser o ativo. O atual continua
                        salvo, com suas disciplinas e tópicos.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <PlanFields form={form} idPrefix="new_plan" />

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Criar plano
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ActivePlanView({
    plans,
    plan,
}: {
    plans: PlanSummary[];
    plan: ActiveStudyPlan;
}) {
    const [pendingSubject, setPendingSubject] = useState<string | null>(null);
    const topicsTotal = plan.subjects.reduce(
        (total, subject) => total + subject.topics.length,
        0,
    );
    // O conteúdo inteiro do plano é substituído pela resposta da troca de
    // plano: a espera só se anuncia se demorar, e a lista nova entra
    // escalonada (a `key` do plano remonta os cards) em vez de trocar seco.
    const [busy, setBusy] = useState(false);
    const switchingClassName = cn(
        'transition-opacity duration-base ease-out',
        busy && 'opacity-50',
    );

    return (
        <>
            <div aria-busy={busy} className={switchingClassName}>
                <PageHeader
                    title={plan.name}
                    subtitle={[
                        plan.type === 'contest' ? 'Concurso' : 'Estudo livre',
                        `${plan.subjects.length} disciplinas`,
                        `${topicsTotal} tópicos`,
                        plan.examDate &&
                            `prova em ${formatExamDate(plan.examDate)}`,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                    actions={
                        <>
                            <EditPlanDialog plan={plan} />
                            <CreatePlanDialog />
                            <Button asChild>
                                <Link href={showFocus()}>Estudar agora</Link>
                            </Button>
                        </>
                    }
                />
            </div>

            <PlanSwitcher
                plans={plans}
                activePlanId={plan.id}
                onBusyChange={setBusy}
            />

            <div
                aria-busy={busy}
                className={cn('flex flex-col gap-6', switchingClassName)}
            >
                <AddSubjectPanel
                    onSubmitStart={setPendingSubject}
                    onSettled={() => setPendingSubject(null)}
                />

                <div
                    key={plan.id}
                    className="stagger-children flex flex-col gap-3"
                >
                    {plan.subjects.map((subject) => (
                        <SubjectRow
                            key={subject.id}
                            subject={subject}
                            showCycles={hasSeveralCycles(plan)}
                        />
                    ))}
                    {pendingSubject && (
                        // `animate-none` cancela o stagger: o eco do que
                        // acabou de ser digitado precisa aparecer no mesmo
                        // frame do clique.
                        <Panel className="animate-none opacity-60">
                            <div className="text-md font-bold text-foreground">
                                {pendingSubject}
                            </div>
                            <div className="mt-0.5 text-xs text-muted-foreground">
                                Adicionando…
                            </div>
                        </Panel>
                    )}
                </div>
            </div>
        </>
    );
}

function EditPlanDialog({ plan }: { plan: ActiveStudyPlan }) {
    const [open, setOpen] = useState(false);
    const form = useForm<PlanFormData>({
        name: plan.name,
        type: plan.type,
        weekly_goal_minutes: plan.weeklyGoalMinutes,
        exam_date: plan.examDate ?? '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.put(updatePlan(plan.id).url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                form.clearErrors();
                form.setData({
                    name: plan.name,
                    type: plan.type,
                    weekly_goal_minutes: plan.weeklyGoalMinutes,
                    exam_date: plan.examDate ?? '',
                });
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline">Editar</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Editar plano</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <PlanFields form={form} idPrefix="edit_plan" />

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Salvar alterações
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AddSubjectPanel({
    onSubmitStart,
    onSettled,
}: {
    onSubmitStart: (name: string) => void;
    onSettled: () => void;
}) {
    const form = useForm({
        name: '',
        goal_minutes: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.transform((data) => ({
            name: data.name,
            goal_minutes: data.goal_minutes
                ? Number(data.goal_minutes) * 60
                : null,
        }));

        onSubmitStart(form.data.name.trim());

        form.post(storeSubject().url, {
            preserveScroll: true,
            showProgress: false,
            onSuccess: () => form.reset(),
            onFinish: onSettled,
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

/**
 * A data da prova viaja no formato do input (aaaa-mm-dd), que é o que o
 * campo devolve; para leitura ela vira dd/mm/aaaa aqui mesmo, sem passar
 * pelo servidor só para mudar de formato.
 */
function formatExamDate(date: string): string {
    return date.split('-').reverse().join('/');
}

/**
 * O ciclo só vira informação quando existe mais de um: numa trilha de um
 * ciclo só, um "Ciclo 1" repetido em cada linha é ruído.
 */
function hasSeveralCycles(plan: ActiveStudyPlan): boolean {
    const cycles = new Set(
        plan.subjects.flatMap((subject) =>
            subject.topics.map((topic) => topic.cycle),
        ),
    );

    return cycles.size > 1;
}

function SubjectRow({
    subject,
    showCycles,
}: {
    subject: PlanSubject;
    showCycles: boolean;
}) {
    const [pendingTopic, setPendingTopic] = useState<string | null>(null);
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
                        {subject.topics.length} tópicos
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
                    <TopicRow
                        key={topic.id}
                        topic={topic}
                        showCycle={showCycles}
                    />
                ))}
                {pendingTopic && (
                    // Eco do tópico recém-digitado, no lugar em que ele vai
                    // nascer, enquanto o servidor confirma.
                    <div className="flex items-center gap-3 rounded-sm px-2 py-1.5 opacity-60">
                        <div className="size-4 shrink-0 rounded-[4px] border border-border-strong" />
                        <span className="flex-1 text-sm text-foreground">
                            {pendingTopic}
                        </span>
                    </div>
                )}
            </div>

            <AddTopicForm
                subjectId={subject.id}
                onSubmitStart={setPendingTopic}
                onSettled={() => setPendingTopic(null)}
            />
        </Panel>
    );
}

function TopicRow({
    topic,
    showCycle,
}: {
    topic: PlanSubject['topics'][number];
    showCycle: boolean;
}) {
    const [pendingComplete, setPendingComplete] = useState(false);
    const completed = topic.completed || pendingComplete;

    function markComplete() {
        setPendingComplete(true);
        router.post(
            completeTopic(topic.id).url,
            {},
            {
                preserveScroll: true,
                showProgress: false,
                onError: () => setPendingComplete(false),
            },
        );
    }

    return (
        <div className="flex items-center gap-3 rounded-sm px-2 py-1.5 transition-colors duration-fast ease-out hover:bg-muted">
            <button
                type="button"
                onClick={completed ? undefined : markComplete}
                disabled={completed}
                aria-label={
                    completed
                        ? `${topic.name} concluído`
                        : `Marcar ${topic.name} como concluído`
                }
                className={cn(
                    'flex size-4 shrink-0 items-center justify-center rounded-[4px] border text-[11px]',
                    'transition-[background-color,border-color,color,box-shadow,scale] duration-fast ease-out',
                    'outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                    'enabled:active:scale-90 enabled:active:duration-instant',
                    completed
                        ? 'border-transparent bg-status-success text-white'
                        : 'border-border-strong bg-transparent hover:border-focus-500',
                )}
            >
                {completed && '✓'}
            </button>
            <span
                className={cn(
                    'flex-1 text-sm transition-colors duration-base ease-out',
                    completed ? 'text-fg-2' : 'text-foreground',
                )}
            >
                {topic.name}
            </span>
            {showCycle && (
                <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                    Ciclo {topic.cycle + 1}
                </span>
            )}
            {topic.reviewStatus && (
                <ReviewStatusBadge status={topic.reviewStatus} />
            )}
        </div>
    );
}

function AddTopicForm({
    subjectId,
    onSubmitStart,
    onSettled,
}: {
    subjectId: number;
    onSubmitStart: (name: string) => void;
    onSettled: () => void;
}) {
    const form = useForm({ name: '' });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.transform((data) => ({ ...data, subject_id: subjectId }));

        onSubmitStart(form.data.name.trim());

        form.post(storeTopic().url, {
            preserveScroll: true,
            showProgress: false,
            onSuccess: () => form.reset(),
            onFinish: onSettled,
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
