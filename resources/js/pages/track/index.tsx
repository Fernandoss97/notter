import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { Panel } from '@/components/panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ProgressBar } from '@/components/ui/progress-bar';
import { ReviewStatusBadge } from '@/components/ui/review-status-badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { index as planIndex } from '@/routes/plans';
import { complete as completeTopic, store as storeTopic } from '@/routes/topics';
import { index } from '@/routes/track';
import type { TrackCycle, TrackPlan, TrackTask } from '@/types/study';

interface TrackIndexProps {
    plan: TrackPlan | null;
    cycles: TrackCycle[];
}

export default function TrackIndex({ plan, cycles }: TrackIndexProps) {
    const [pendingTask, setPendingTask] = useState<string | null>(null);
    const total = cycles.reduce((sum, cycle) => sum + cycle.total, 0);
    const done = cycles.reduce((sum, cycle) => sum + cycle.done, 0);

    return (
        <>
            <Head title="Trilha" />
            <Page maxWidth="narrow">
                {!plan ? (
                    <NoPlanPanel />
                ) : (
                    <>
                        <PageHeader
                            title="Trilha"
                            subtitle={`${plan.name} · ${done} de ${total} tarefas concluídas`}
                            actions={
                                <Button variant="outline" asChild>
                                    <Link href={planIndex()}>Ver plano</Link>
                                </Button>
                            }
                        />

                        <AddTaskPanel
                            subjects={plan.subjects}
                            currentCycle={cycles.at(-1)?.cycle ?? 0}
                            onSubmitStart={setPendingTask}
                            onSettled={() => setPendingTask(null)}
                        />

                        <div className="stagger-children flex flex-col gap-4">
                            {cycles.map((cycle) => (
                                <CyclePanel key={cycle.cycle} cycle={cycle} />
                            ))}
                            {pendingTask && (
                                // `animate-none` cancela o stagger: o eco do
                                // que acabou de ser digitado aparece no mesmo
                                // frame do clique.
                                <Panel className="animate-none opacity-60">
                                    <div className="text-sm text-foreground">
                                        {pendingTask}
                                    </div>
                                    <div className="mt-0.5 text-xs text-muted-foreground">
                                        Adicionando…
                                    </div>
                                </Panel>
                            )}
                            <EmptyCycles
                                visible={cycles.length === 0 && !pendingTask}
                            />
                        </div>
                    </>
                )}
            </Page>
        </>
    );
}

TrackIndex.layout = {
    breadcrumbs: [{ title: 'Trilha', href: index() }],
};

function NoPlanPanel() {
    return (
        <Panel title="Trilha">
            <p className="text-sm text-fg-2">
                A trilha é a fila de tarefas do plano ativo. Crie um plano de
                estudos para começar a montá-la.
            </p>
            <Button asChild className="w-fit">
                <Link href={planIndex()}>Criar plano de estudos</Link>
            </Button>
        </Panel>
    );
}

/**
 * O estado vazio fica montado e some via `hidden`: ele é o último filho da
 * lista, então esconder não move nada acima dele, e continuar montado evita
 * que a primeira tarefa adicionada troque um nó por outro no mesmo lugar.
 * `aria-hidden` + `inert` tiram-no também da leitura assistiva.
 */
function EmptyCycles({ visible }: { visible: boolean }) {
    return (
        <p
            data-slot="track-empty"
            data-visible={visible}
            aria-hidden={!visible}
            inert={!visible || undefined}
            hidden={!visible}
            className={cn('text-sm text-fg-2', !visible && 'hidden')}
        >
            Nenhuma tarefa na trilha ainda. Adicione a primeira acima — ela abre
            o ciclo 1.
        </p>
    );
}

function CyclePanel({ cycle }: { cycle: TrackCycle }) {
    const progress =
        cycle.total > 0 ? Math.round((cycle.done / cycle.total) * 100) : 0;

    return (
        <Panel>
            <div className="flex items-center justify-between gap-4">
                <div>
                    <div className="text-md font-bold text-foreground">
                        Ciclo {cycle.cycle + 1}
                    </div>
                    <div className="mt-0.5 text-xs text-muted-foreground">
                        {cycle.done} de {cycle.total} tarefas
                    </div>
                </div>
                <div className="w-40">
                    <ProgressBar value={progress} showLabel />
                </div>
            </div>

            <div className="flex flex-col gap-1.5">
                {cycle.tasks.map((task) => (
                    <TaskRow key={task.id} task={task} />
                ))}
            </div>
        </Panel>
    );
}

function TaskRow({ task }: { task: TrackTask }) {
    const [pendingComplete, setPendingComplete] = useState(false);
    const completed = task.completed || pendingComplete;

    function markComplete() {
        setPendingComplete(true);
        router.post(
            completeTopic(task.id).url,
            {},
            {
                preserveScroll: true,
                showProgress: false,
                onError: () => setPendingComplete(false),
            },
        );
    }

    return (
        <div
            data-slot="track-task"
            className="flex items-center gap-3 rounded-sm px-2 py-1.5 transition-colors duration-fast ease-out hover:bg-muted"
        >
            <button
                type="button"
                onClick={completed ? undefined : markComplete}
                disabled={completed}
                aria-label={
                    completed
                        ? `${task.name} concluída`
                        : `Marcar ${task.name} como concluída`
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

            <span className="w-8 shrink-0 text-right text-xs tabular-nums text-muted-foreground">
                {task.number}
            </span>

            <div className="flex min-w-0 flex-1 flex-col">
                <span
                    className={cn(
                        'truncate text-sm transition-colors duration-base ease-out',
                        completed ? 'text-fg-2' : 'text-foreground',
                    )}
                >
                    {task.name}
                </span>
                <span className="text-xs text-muted-foreground">
                    {task.subjectName}
                    {task.completedOn && ` · concluída em ${task.completedOn}`}
                </span>
            </div>

            {task.reviewStatus && (
                <ReviewStatusBadge status={task.reviewStatus} />
            )}
        </div>
    );
}

/**
 * O ciclo aparece 1-based na tela e viaja 0-based para o servidor: "ciclo
 * 1" lê melhor que "ciclo 0", e o banco continua contando do zero.
 */
function AddTaskPanel({
    subjects,
    currentCycle,
    onSubmitStart,
    onSettled,
}: {
    subjects: TrackPlan['subjects'];
    currentCycle: number;
    onSubmitStart: (name: string) => void;
    onSettled: () => void;
}) {
    const form = useForm({
        subject_id: '',
        name: '',
        cycle: String(currentCycle + 1),
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.transform((data) => ({
            subject_id: Number(data.subject_id),
            name: data.name,
            cycle: Math.max(0, Number(data.cycle) - 1),
        }));

        onSubmitStart(form.data.name.trim());

        form.post(storeTopic().url, {
            preserveScroll: true,
            showProgress: false,
            onSuccess: () =>
                form.setData((current) => ({ ...current, name: '' })),
            onFinish: onSettled,
        });
    }

    return (
        <Panel title="Adicionar tarefa">
            <form onSubmit={submit} className="flex flex-wrap items-end gap-4">
                <div className="flex w-48 flex-col gap-2">
                    <Label htmlFor="task_subject">Disciplina</Label>
                    <Select
                        value={form.data.subject_id}
                        onValueChange={(value) =>
                            form.setData('subject_id', value)
                        }
                    >
                        <SelectTrigger id="task_subject" className="w-full">
                            <SelectValue placeholder="Selecione" />
                        </SelectTrigger>
                        <SelectContent>
                            {subjects.map((subject) => (
                                <SelectItem
                                    key={subject.id}
                                    value={String(subject.id)}
                                >
                                    {subject.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {form.errors.subject_id && (
                        <p className="text-sm text-destructive">
                            {form.errors.subject_id}
                        </p>
                    )}
                </div>

                <div className="flex min-w-[200px] flex-1 flex-col gap-2">
                    <Label htmlFor="task_name">Tarefa</Label>
                    <Input
                        id="task_name"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        placeholder="Estudo da Aula 00 e questões do PDF"
                    />
                    {form.errors.name && (
                        <p className="text-sm text-destructive">
                            {form.errors.name}
                        </p>
                    )}
                </div>

                <div className="flex w-24 flex-col gap-2">
                    <Label htmlFor="task_cycle">Ciclo</Label>
                    <Input
                        id="task_cycle"
                        type="number"
                        min={1}
                        value={form.data.cycle}
                        onChange={(event) =>
                            form.setData('cycle', event.target.value)
                        }
                    />
                </div>

                <Button type="submit" disabled={form.processing}>
                    Adicionar
                </Button>
            </form>
        </Panel>
    );
}
