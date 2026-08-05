import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Panel } from '@/components/panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ProgressBar } from '@/components/ui/progress-bar';
import { ReviewStatusBadge } from '@/components/ui/review-status-badge';
import { SegmentedControl } from '@/components/ui/segmented-control';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useLiveElapsedSeconds } from '@/hooks/use-live-elapsed-seconds';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import {
    complete as completeSession,
    pause as pauseSession,
    resume as resumeSession,
    show,
    start as startSession,
} from '@/routes/focus';
import { index as showPlan } from '@/routes/plans';
import type {
    ActivityType,
    FocusSession,
    PomodoroSettingSummary,
    StudyPlanSummary,
} from '@/types/study';

const ACTIVITY_OPTIONS: { value: ActivityType; label: string }[] = [
    { value: 'reading', label: 'Leitura' },
    { value: 'exercises', label: 'Exercícios' },
    { value: 'review', label: 'Revisão' },
    { value: 'practice', label: 'Prática' },
];

function formatElapsed(totalSeconds: number): string {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    const mm = String(minutes).padStart(2, '0');
    const ss = String(seconds).padStart(2, '0');

    return hours > 0 ? `${hours}:${mm}:${ss}` : `${mm}:${ss}`;
}

interface FocusShowProps {
    plan: StudyPlanSummary | null;
    session: FocusSession | null;
    pomodoroSetting: PomodoroSettingSummary;
    todayCycles: number;
}

export default function FocusShow({
    plan,
    session,
    pomodoroSetting,
    todayCycles,
}: FocusShowProps) {
    return (
        <>
            <Head title="Foco" />
            <div className="mx-auto flex max-w-app flex-col gap-7 p-10">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-bold text-foreground">
                        Sessão de foco
                    </h1>
                    <Button variant="ghost" asChild>
                        <Link href={dashboard()}>← Voltar ao dashboard</Link>
                    </Button>
                </div>

                {!plan ? (
                    <EmptyPlanState />
                ) : session ? (
                    <ActiveSessionView
                        session={session}
                        plan={plan}
                        pomodoroSetting={pomodoroSetting}
                        todayCycles={todayCycles}
                    />
                ) : (
                    <StartSessionPanel plan={plan} />
                )}
            </div>
        </>
    );
}

FocusShow.layout = {
    breadcrumbs: [{ title: 'Foco', href: show() }],
};

function EmptyPlanState() {
    return (
        <Panel>
            <p className="text-sm text-fg-2">
                Você ainda não tem um plano de estudos ativo. Crie um plano para
                começar uma sessão de foco.
            </p>
            <Button asChild className="w-fit">
                <Link href={showPlan()}>Criar plano de estudos</Link>
            </Button>
        </Panel>
    );
}

function StartSessionPanel({ plan }: { plan: StudyPlanSummary }) {
    const form = useForm({
        subject_id: '',
        topic_id: '',
    });

    const subject = plan.subjects.find(
        (s) => String(s.id) === form.data.subject_id,
    );

    function submit(event: FormEvent) {
        event.preventDefault();

        form.transform((data) => ({
            subject_id: data.subject_id || null,
            topic_id: data.topic_id || null,
        }));

        form.post(startSession().url, { preserveScroll: true });
    }

    return (
        <Panel title="Iniciar sessão">
            <form onSubmit={submit} className="flex flex-col gap-4">
                <div className="grid grid-cols-2 gap-4">
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="subject_id">
                            Disciplina (opcional)
                        </Label>
                        <Select
                            value={form.data.subject_id}
                            onValueChange={(value) => {
                                form.setData('subject_id', value);
                                form.setData('topic_id', '');
                            }}
                        >
                            <SelectTrigger id="subject_id" className="w-full">
                                <SelectValue placeholder="Selecione uma disciplina" />
                            </SelectTrigger>
                            <SelectContent>
                                {plan.subjects.map((s) => (
                                    <SelectItem key={s.id} value={String(s.id)}>
                                        {s.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.subject_id} />
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label htmlFor="topic_id">Tópico (opcional)</Label>
                        <Select
                            value={form.data.topic_id}
                            onValueChange={(value) =>
                                form.setData('topic_id', value)
                            }
                            disabled={!subject || subject.topics.length === 0}
                        >
                            <SelectTrigger id="topic_id" className="w-full">
                                <SelectValue placeholder="Selecione um tópico" />
                            </SelectTrigger>
                            <SelectContent>
                                {subject?.topics.map((t) => (
                                    <SelectItem key={t.id} value={String(t.id)}>
                                        {t.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.topic_id} />
                    </div>
                </div>

                <Button
                    type="submit"
                    disabled={form.processing}
                    className="w-fit"
                >
                    Iniciar sessão de foco
                </Button>
            </form>
        </Panel>
    );
}

function ActiveSessionView({
    session,
    plan,
    pomodoroSetting,
    todayCycles,
}: {
    session: FocusSession;
    plan: StudyPlanSummary;
    pomodoroSetting: PomodoroSettingSummary;
    todayCycles: number;
}) {
    const elapsedSeconds = useLiveElapsedSeconds(session);
    const paused = session.state === 'paused';
    const progress = Math.min(
        100,
        Math.round((elapsedSeconds / session.plannedSeconds) * 100),
    );

    const currentSubject = plan.subjects.find(
        (s) => s.id === session.subjectId,
    );
    const currentTopic = currentSubject?.topics.find(
        (t) => t.id === session.topicId,
    );

    function toggle() {
        const target = paused ? resumeSession() : pauseSession();
        router.post(target.url, {}, { preserveScroll: true });
    }

    const cyclesTotal = pomodoroSetting.cyclesBeforeLongBreak;
    const cycles = Array.from(
        { length: cyclesTotal },
        (_, i) => i < todayCycles,
    );

    return (
        <div className="grid grid-cols-[1fr_360px] items-start gap-6">
            <div className="flex flex-col items-center justify-center gap-6 rounded-md border border-border bg-card p-10 shadow-sm">
                {currentTopic && (
                    <ReviewStatusBadge
                        status={currentTopic.reviewStatus ?? 'ontrack'}
                        label={`${currentSubject?.name} · ${currentTopic.name}`}
                    />
                )}

                <span
                    className={cn(
                        'flex items-center gap-2 text-xs font-bold tracking-wide uppercase',
                        paused ? 'text-pausa-600' : 'text-focus-500',
                    )}
                >
                    <span
                        className={cn(
                            'size-1.5 rounded-full',
                            paused ? 'bg-pausa-500' : 'bg-focus-500',
                        )}
                    />
                    {paused ? 'Pausado' : 'Em foco'}
                </span>

                <span className="font-mono text-timer text-foreground tabular-nums">
                    {formatElapsed(elapsedSeconds)}
                </span>

                <div className="w-full max-w-[320px]">
                    <ProgressBar
                        value={progress}
                        color={paused ? 'pausa' : 'focus'}
                    />
                </div>

                <Button
                    onClick={toggle}
                    variant={paused ? 'default' : 'secondary'}
                >
                    {paused ? 'Retomar' : 'Pausar'}
                </Button>
            </div>

            <div className="flex flex-col gap-5">
                <Panel title="Ciclo de hoje">
                    <div className="flex gap-2">
                        {cycles.map((done, index) => (
                            <div
                                key={index}
                                className={cn(
                                    'h-2 flex-1 rounded-full',
                                    done ? 'bg-focus-500' : 'bg-muted',
                                )}
                            />
                        ))}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        {todayCycles} de {cyclesTotal} concluídos hoje
                    </p>
                </Panel>

                <CompleteSessionPanel plan={plan} session={session} />

                <div className="rounded-md border border-pausa-200 bg-pausa-100 p-5">
                    <div className="text-xs font-semibold tracking-wide text-pausa-600 uppercase">
                        Próxima pausa
                    </div>
                    <p className="mt-1.5 text-sm text-fg-2">
                        {pomodoroSetting.shortBreakMinutes} minutos de descanso.
                    </p>
                </div>
            </div>
        </div>
    );
}

function CompleteSessionPanel({
    plan,
    session,
}: {
    plan: StudyPlanSummary;
    session: FocusSession;
}) {
    const form = useForm({
        subject_id: session.subjectId ? String(session.subjectId) : '',
        topic_id: session.topicId ? String(session.topicId) : '',
        activity_type: session.activityType ?? ('exercises' as ActivityType),
        exercises_total: '',
        exercises_correct: '',
    });

    const subject = plan.subjects.find(
        (s) => String(s.id) === form.data.subject_id,
    );

    function submit(event: FormEvent) {
        event.preventDefault();

        form.transform((data) => ({
            subject_id: data.subject_id || null,
            topic_id: data.topic_id || null,
            activity_type: data.activity_type,
            exercises_total: data.exercises_total || null,
            exercises_correct: data.exercises_correct || null,
        }));

        form.post(completeSession().url, { preserveScroll: true });
    }

    return (
        <Panel title="Registrar ao final">
            <form onSubmit={submit} className="flex flex-col gap-4">
                <div className="flex flex-col gap-2">
                    <Label htmlFor="complete_subject_id">Disciplina</Label>
                    <Select
                        value={form.data.subject_id}
                        onValueChange={(value) => {
                            form.setData('subject_id', value);
                            form.setData('topic_id', '');
                        }}
                    >
                        <SelectTrigger
                            id="complete_subject_id"
                            className="w-full"
                        >
                            <SelectValue placeholder="Selecione uma disciplina" />
                        </SelectTrigger>
                        <SelectContent>
                            {plan.subjects.map((s) => (
                                <SelectItem key={s.id} value={String(s.id)}>
                                    {s.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.subject_id} />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="complete_topic_id">Tópico</Label>
                    <Select
                        value={form.data.topic_id}
                        onValueChange={(value) =>
                            form.setData('topic_id', value)
                        }
                        disabled={!subject || subject.topics.length === 0}
                    >
                        <SelectTrigger
                            id="complete_topic_id"
                            className="w-full"
                        >
                            <SelectValue placeholder="Selecione um tópico" />
                        </SelectTrigger>
                        <SelectContent>
                            {subject?.topics.map((t) => (
                                <SelectItem key={t.id} value={String(t.id)}>
                                    {t.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.topic_id} />
                </div>

                <div className="flex flex-col gap-2">
                    <Label>Tipo de atividade</Label>
                    <SegmentedControl
                        options={ACTIVITY_OPTIONS}
                        value={form.data.activity_type}
                        onValueChange={(value) =>
                            form.setData('activity_type', value as ActivityType)
                        }
                    />
                    <InputError message={form.errors.activity_type} />
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="exercises_total">
                            Exercícios resolvidos
                        </Label>
                        <Input
                            id="exercises_total"
                            type="number"
                            min={0}
                            value={form.data.exercises_total}
                            onChange={(event) =>
                                form.setData(
                                    'exercises_total',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.exercises_total} />
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label htmlFor="exercises_correct">Acertos</Label>
                        <Input
                            id="exercises_correct"
                            type="number"
                            min={0}
                            value={form.data.exercises_correct}
                            onChange={(event) =>
                                form.setData(
                                    'exercises_correct',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.exercises_correct} />
                    </div>
                </div>

                <Button
                    type="submit"
                    disabled={form.processing}
                    variant="secondary"
                >
                    Encerrar sessão
                </Button>
            </form>
        </Panel>
    );
}
