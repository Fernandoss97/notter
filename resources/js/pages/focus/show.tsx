import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState, useSyncExternalStore } from 'react';
import type { FormEvent } from 'react';
import { FocusTimer } from '@/components/focus-timer';
import InputError from '@/components/input-error';
import { Page } from '@/components/page';
import { Panel } from '@/components/panel';
import { PlanSwitcher } from '@/components/plan-switcher';
import { ReviewQueue } from '@/components/review-queue';
import { SessionNotes } from '@/components/session-notes';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ProgressBar } from '@/components/ui/progress-bar';
import { SegmentedControl } from '@/components/ui/segmented-control';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { ZenThemePicker } from '@/components/zen-theme-picker';
import { useDocumentTitle } from '@/hooks/use-document-title';
import { useFullscreen } from '@/hooks/use-fullscreen';
import { useHiddenTime } from '@/hooks/use-hidden-time';
import { useHotkeys } from '@/hooks/use-hotkeys';
import { useLiveElapsedSeconds } from '@/hooks/use-live-elapsed-seconds';
import { usePhaseAlert } from '@/hooks/use-phase-alert';
import { useWakeLock } from '@/hooks/use-wake-lock';
import { useZenTheme } from '@/hooks/use-zen-theme';
import { notificationsBlocked, prepareAlerts } from '@/lib/focus-alerts';
import { formatClock, formatDurationMinutes } from '@/lib/format-duration';
import { ACTIVITY_TYPE_LABEL } from '@/lib/study-labels';
import { suggestNextStudy } from '@/lib/study-suggestion';
import { cn } from '@/lib/utils';
import { zenThemeVars } from '@/lib/zen-themes';
import {
    advance as advancePhase,
    complete as completeSession,
    extend as extendPhase,
    pause as pauseSession,
    resume as resumeSession,
    show,
    start as startSession,
} from '@/routes/focus';
import { index as showCycle } from '@/routes/cycle';
import { index as showPlan } from '@/routes/plans';
import type {
    ActivityType,
    CycleBlockSummary,
    DueReview,
    FocusSession,
    PlanSummary,
    PomodoroSettingSummary,
    StudyPlanSummary,
    TodayProgress,
} from '@/types/study';

const ACTIVITY_OPTIONS: { value: ActivityType; label: string }[] = (
    Object.entries(ACTIVITY_TYPE_LABEL) as [ActivityType, string][]
).map(([value, label]) => ({ value, label }));

interface FocusShowProps {
    plans: PlanSummary[];
    plan: StudyPlanSummary | null;
    session: FocusSession | null;
    pomodoroSetting: PomodoroSettingSummary;
    currentBlock: CycleBlockSummary | null;
    todayPomodoros: number;
    today: TodayProgress;
    dueReviews: DueReview[];
}

export default function FocusShow({
    plans,
    plan,
    session,
    pomodoroSetting,
    currentBlock = null,
    todayPomodoros,
    today,
    dueReviews,
}: FocusShowProps) {
    return (
        <>
            <Head title="Foco" />
            {!plan ? (
                <Page>
                    <EmptyPlanState />
                </Page>
            ) : session ? (
                <ActiveSessionView
                    session={session}
                    plan={plan}
                    pomodoroSetting={pomodoroSetting}
                    currentBlock={currentBlock}
                    todayPomodoros={todayPomodoros}
                    today={today}
                    dueReviews={dueReviews}
                />
            ) : (
                <Page>
                    <IdleView
                        plans={plans}
                        plan={plan}
                        today={today}
                        todayPomodoros={todayPomodoros}
                        dueReviews={dueReviews}
                        currentBlock={currentBlock}
                        focusMinutes={pomodoroSetting.focusMinutes}
                    />
                </Page>
            )}
        </>
    );
}

FocusShow.layout = {
    breadcrumbs: [{ title: 'Foco', href: show() }],
};

function EmptyPlanState() {
    return (
        <Panel>
            <div className="flex flex-col items-center gap-4 py-10 text-center">
                <h1 className="text-2xl font-extrabold tracking-tight text-foreground">
                    Comece pelo seu plano
                </h1>
                <p className="max-w-md text-sm text-fg-2">
                    Um plano guarda as disciplinas e os tópicos que você vai
                    estudar. É a partir dele que as sessões de foco e as
                    revisões espaçadas acontecem.
                </p>
                <Button asChild>
                    <Link href={showPlan()}>Criar plano de estudos</Link>
                </Button>
            </div>
        </Panel>
    );
}

/**
 * A tela sem sessão em andamento: a decisão do que estudar, com o
 * progresso do dia e a fila de revisões ao lado para embasá-la.
 *
 * É também o único momento em que trocar de plano faz sentido nesta tela —
 * com o cronômetro correndo, a sessão já pertence ao plano em que começou,
 * e o seletor convidaria a uma troca que não a acompanharia.
 */
function IdleView({
    plans,
    plan,
    today,
    todayPomodoros,
    dueReviews,
    currentBlock,
    focusMinutes,
}: {
    plans: PlanSummary[];
    plan: StudyPlanSummary;
    today: TodayProgress;
    todayPomodoros: number;
    dueReviews: DueReview[];
    currentBlock: CycleBlockSummary | null;
    focusMinutes: number;
}) {
    const [preset, setPreset] = useState<{
        subjectId: number;
        topicId: number | null;
    } | null>(null);
    const [busy, setBusy] = useState(false);
    const switchingClassName = cn(
        'transition-opacity duration-base ease-out',
        busy && 'opacity-50',
    );

    return (
        <>
            <div
                aria-busy={busy}
                className={cn(
                    'flex items-center justify-between',
                    switchingClassName,
                )}
            >
                <div>
                    <h1 className="text-2xl font-extrabold tracking-tight text-foreground">
                        Pronto para focar
                    </h1>
                    <p className="mt-0.5 text-sm text-fg-2">
                        {plan.name} · ciclos de {focusMinutes} minutos
                    </p>
                </div>
                <TodaySummary today={today} pomodoros={todayPomodoros} inline />
            </div>

            <PlanSwitcher
                plans={plans}
                activePlanId={plan.id}
                onBusyChange={setBusy}
            />

            <div
                aria-busy={busy}
                className={cn(
                    'grid grid-cols-[1fr_360px] items-start gap-6',
                    switchingClassName,
                )}
            >
                {/* A `key` remonta o formulário quando a fila ao lado
                    escolhe um tópico, ou quando a troca de plano traz
                    outras disciplinas: é o jeito React de reiniciar estado
                    a partir de uma prop, sem sincronizar em efeito. */}
                <div className="flex flex-col gap-6">
                    <CurrentBlockPanel block={currentBlock} />
                    <StartSessionPanel
                        key={
                            preset
                                ? `${preset.subjectId}-${preset.topicId}`
                                : `suggested-${plan.id}-${currentBlock?.id ?? 0}`
                        }
                        plan={plan}
                        preset={preset}
                        dueReviews={dueReviews}
                        currentBlock={currentBlock}
                    />
                </div>
                <ReviewQueue
                    reviews={dueReviews}
                    onStudy={(review) =>
                        setPreset({
                            subjectId: review.subjectId,
                            topicId: review.topicId,
                        })
                    }
                />
            </div>
        </>
    );
}

function TodaySummary({
    today,
    pomodoros,
    inline = false,
}: {
    today: TodayProgress;
    pomodoros: number;
    inline?: boolean;
}) {
    const progress =
        today.goalMinutes > 0
            ? Math.min(
                  100,
                  Math.round((today.minutes / today.goalMinutes) * 100),
              )
            : 0;

    const content = (
        <>
            <div className="flex items-baseline justify-between gap-3">
                <span className="font-mono text-lg font-semibold text-foreground tabular-nums">
                    {formatDurationMinutes(today.minutes)}
                </span>
                <span className="text-xs text-muted-foreground">
                    meta {formatDurationMinutes(today.goalMinutes)}
                </span>
            </div>
            <ProgressBar value={progress} color="focus" height={6} />
            <span className="text-xs text-muted-foreground">
                {pomodoros} pomodoro{pomodoros === 1 ? '' : 's'} hoje
            </span>
        </>
    );

    if (inline) {
        return (
            <div className="flex w-56 flex-col gap-1.5 text-right">
                {content}
            </div>
        );
    }

    return <Panel title="Hoje">{content}</Panel>;
}

/**
 * De quem é a vez no ciclo, e quanto falta do bloco.
 *
 * Fica montado mesmo sem ciclo montado, escondido — assim montar o rodízio
 * não empurra o painel de iniciar sessão para baixo na volta seguinte.
 * `aria-hidden` + `inert` tiram-no também da leitura assistiva.
 */
function CurrentBlockPanel({ block }: { block: CycleBlockSummary | null }) {
    const visible = block !== null;

    return (
        <div
            data-slot="focus-current-block"
            data-visible={visible}
            aria-hidden={!visible}
            inert={!visible || undefined}
            hidden={!visible}
            className={cn(!visible && 'hidden')}
        >
            <Panel title="A vez do ciclo">
                {block && (
                    <>
                        <div className="flex items-baseline justify-between gap-3">
                            <span className="text-md font-bold text-foreground">
                                {block.subjectName}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                volta {block.lap}
                            </span>
                        </div>
                        <ProgressBar value={block.progressPercent} />
                        <p className="text-xs text-muted-foreground">
                            {formatDurationMinutes(
                                Math.floor(block.elapsedSeconds / 60),
                            )}{' '}
                            de {formatDurationMinutes(block.targetMinutes)} ·
                            faltam{' '}
                            {formatDurationMinutes(block.remainingMinutes)}
                        </p>
                        <Button variant="ghost" size="sm" asChild className="w-fit">
                            <Link href={showCycle()}>Ver o ciclo</Link>
                        </Button>
                    </>
                )}
            </Panel>
        </div>
    );
}

function StartSessionPanel({
    plan,
    preset,
    dueReviews,
    currentBlock,
}: {
    plan: StudyPlanSummary;
    preset: { subjectId: number; topicId: number | null } | null;
    dueReviews: DueReview[];
    currentBlock: CycleBlockSummary | null;
}) {
    // A ordem de quem decide o que já vem escolhido: o clique do usuário na
    // fila, depois a vez do ciclo, depois a revisão mais atrasada (a mesma
    // primeira linha da fila ao lado), e só então a próxima tarefa da trilha.
    //
    // O bloco do rodízio passa à frente da revisão porque ele decide a
    // *disciplina*: começar por outra faria a sessão não creditar o bloco e
    // o ciclo pararia de andar sem ninguém entender por quê. Dentro da
    // disciplina da vez, a trilha continua escolhendo a tarefa.
    const dueReview = dueReviews[0]
        ? { subjectId: dueReviews[0].subjectId, topicId: dueReviews[0].topicId }
        : null;
    const initial =
        preset ??
        (currentBlock
            ? suggestNextStudy(plan.subjects, currentBlock.subjectId)
            : null) ??
        dueReview ??
        suggestNextStudy(plan.subjects);

    const form = useForm({
        subject_id: initial ? String(initial.subjectId) : '',
        topic_id: initial?.topicId ? String(initial.topicId) : '',
    });

    const subject = plan.subjects.find(
        (s) => String(s.id) === form.data.subject_id,
    );

    function submit(event: FormEvent) {
        event.preventDefault();

        // O clique que inicia a sessão é a janela em que o navegador
        // libera o som e a permissão de notificação do fim do ciclo.
        prepareAlerts();

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
                    {form.processing && <Spinner />}
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
    currentBlock,
    todayPomodoros,
    today,
    dueReviews,
}: {
    session: FocusSession;
    plan: StudyPlanSummary;
    pomodoroSetting: PomodoroSettingSummary;
    currentBlock: CycleBlockSummary | null;
    todayPomodoros: number;
    today: TodayProgress;
    dueReviews: DueReview[];
}) {
    const elapsedSeconds = useLiveElapsedSeconds(session);
    const [pendingPaused, setPendingPaused] = useState<boolean | null>(null);
    const [zen, setZen] = useState(false);
    const [completeOpen, setCompleteOpen] = useState(false);
    const paused = pendingPaused ?? session.state === 'paused';
    const onBreak = session.phase !== 'focus';
    const finished = elapsedSeconds >= session.plannedSeconds;
    const alert = usePhaseAlert(session.state);
    const { zenTheme, setZenTheme } = useZenTheme();
    const [hiddenTime, setHiddenTime] = useHiddenTime();

    // A tela não pode apagar no meio do ciclo — ler ou resolver questão
    // no papel não gera atividade nenhuma para o sistema.
    useWakeLock(session.state === 'running');

    // O zen ocupa a tela inteira, e sair da tela cheia (Esc, F11) sai do
    // zen junto: em tela cheia o Esc nunca chega ao atalho.
    useFullscreen(zen, () => setZen(false));

    const currentSubject = plan.subjects.find(
        (s) => s.id === session.subjectId,
    );
    const currentTopic = currentSubject?.topics.find(
        (t) => t.id === session.topicId,
    );

    function toggle() {
        // Retomar (e começar a próxima fase) é gesto do usuário: a única
        // janela em que o navegador destrava o áudio do aviso.
        prepareAlerts();

        const target = paused ? resumeSession() : pauseSession();
        setPendingPaused(!paused);
        router.post(
            target.url,
            {},
            {
                preserveScroll: true,
                showProgress: false,
                onFinish: () => setPendingPaused(null),
            },
        );
    }

    function advance(skip = false) {
        prepareAlerts();

        router.post(advancePhase().url, skip ? { skip: true } : {}, {
            preserveScroll: true,
            showProgress: false,
        });
    }

    function extend() {
        router.post(
            extendPhase().url,
            {},
            { preserveScroll: true, showProgress: false },
        );
    }

    // Fechar o ciclo quando o tempo acaba é trabalho da barra de sessão
    // (ver use-phase-end): ela está montada em todas as telas, inclusive
    // nesta, e fazer o mesmo aqui dispararia o avanço duas vezes. O botão
    // do cronômetro continua servindo para o avanço manual.

    const remainingSeconds = Math.max(
        0,
        session.plannedSeconds - elapsedSeconds,
    );

    const titleState = paused
        ? 'Pausado'
        : onBreak
          ? 'Intervalo'
          : (currentSubject?.name ?? 'Foco');

    // Sem o relógio no título: a aba fica no canto do olho o tempo todo, e
    // é por ela que o tempo escondido voltaria.
    useDocumentTitle(
        hiddenTime
            ? titleState
            : `${formatClock(remainingSeconds)} · ${titleState}`,
    );

    useHotkeys({
        ' ': () => (finished ? advance() : toggle()),
        f: () => setZen((on) => !on),
        escape: () => setZen(false),
        e: () => setCompleteOpen(true),
        t: () => setHiddenTime(!hiddenTime),
    });

    const timer = (
        <FocusTimer
            phase={session.phase}
            paused={paused}
            elapsedSeconds={elapsedSeconds}
            plannedSeconds={session.plannedSeconds}
            completedCycles={session.completedCycles}
            cyclesBeforeLongBreak={pomodoroSetting.cyclesBeforeLongBreak}
            subjectLabel={currentSubject?.name ?? null}
            topicLabel={currentTopic?.name ?? null}
            reviewStatus={currentTopic?.reviewStatus}
            zen={zen}
            hideTime={hiddenTime}
            alertPhase={alert?.phase ?? null}
            onToggle={toggle}
            onAdvance={() => advance()}
            onSkipBreak={() => advance(true)}
            onExtend={extend}
        />
    );

    const hideTimeButton = (
        <Button variant="ghost" onClick={() => setHiddenTime(!hiddenTime)}>
            {hiddenTime ? 'Mostrar tempo' : 'Ocultar tempo'}
        </Button>
    );

    if (zen) {
        return (
            <div
                data-zen-theme={zenTheme.id}
                style={zenThemeVars(zenTheme)}
                // O fundo puxa um pouco da cor escolhida no centro: no
                // zen o cronômetro é a única coisa na tela, e sem isso a
                // cor tema apareceria só em detalhes de poucos pixels.
                className="fixed inset-0 z-50 flex flex-col items-center justify-center gap-8 bg-background bg-[radial-gradient(circle_at_50%_38%,var(--focus-100),var(--background)_62%)]"
            >
                {timer}

                <div className="flex flex-col items-center gap-4">
                    <ZenThemePicker
                        value={zenTheme.id}
                        onValueChange={setZenTheme}
                    />
                    <p className="text-xs text-muted-foreground">
                        Espaço pausa · T oculta o tempo · E encerra · Esc sai do
                        modo zen
                    </p>
                    <div className="flex items-center gap-2">
                        {hideTimeButton}
                        <Button variant="ghost" onClick={() => setZen(false)}>
                            Sair do modo zen (Esc)
                        </Button>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <Page>
            <div className="grid grid-cols-[1fr_340px] items-start gap-6">
                <div className="flex flex-col gap-4">
                    {timer}
                    <div className="flex items-center justify-center gap-2">
                        <Button variant="ghost" onClick={() => setZen(true)}>
                            Modo zen
                        </Button>
                        {hideTimeButton}
                        <CompleteSessionDialog
                            plan={plan}
                            session={session}
                            open={completeOpen}
                            onOpenChange={setCompleteOpen}
                            askForDetails={pomodoroSetting.logSessionOnFinish}
                        />
                    </div>
                    <p className="text-center text-xs text-muted-foreground">
                        Espaço pausa · F entra no modo zen · T oculta o tempo ·
                        E encerra
                    </p>
                    <BlockedNotificationsHint />
                </div>

                <div className="flex flex-col gap-5">
                    <TodaySummary today={today} pomodoros={todayPomodoros} />

                    <CurrentBlockPanel block={currentBlock} />

                    <SessionNotes initialNotes={session.notes} />

                    <ReviewQueue
                        reviews={dueReviews}
                        // Modo silencioso: durante o foco a fila para de
                        // disputar atenção, mas continua ali.
                        muted={
                            pomodoroSetting.silentModeDuringFocus &&
                            !onBreak &&
                            !paused
                        }
                    />

                    <div
                        className={cn(
                            'rounded-md border p-5 transition-colors duration-base ease-out',
                            onBreak
                                ? 'border-focus-200 bg-focus-100'
                                : 'border-pausa-200 bg-pausa-100',
                        )}
                    >
                        <div
                            className={cn(
                                'text-xs font-semibold tracking-wide uppercase',
                                onBreak ? 'text-focus-500' : 'text-pausa-600',
                            )}
                        >
                            {onBreak ? 'Próximo foco' : 'Próxima pausa'}
                        </div>
                        <p className="mt-1.5 text-sm text-fg-2">
                            {onBreak
                                ? `${pomodoroSetting.focusMinutes} minutos de estudo.`
                                : `${pomodoroSetting.shortBreakMinutes} minutos de descanso — a cada ${pomodoroSetting.cyclesBeforeLongBreak} ciclos, ${pomodoroSetting.longBreakMinutes}.`}
                        </p>
                    </div>
                </div>
            </div>
        </Page>
    );
}

/** A permissão não muda sozinha enquanto a tela está aberta. */
const noopSubscribe = () => () => {};

/**
 * Notificação negada é um beco sem saída silencioso: a pessoa confia que
 * o navegador vai chamá-la de volta e ele nunca chama. Dizer isso vale
 * mais que insistir na permissão, que o navegador não pergunta de novo.
 *
 * A leitura passa por `useSyncExternalStore` porque
 * `Notification.permission` é do navegador e não existe no servidor: o
 * snapshot do servidor é "não bloqueado" e a verdade chega na hidratação.
 */
function BlockedNotificationsHint() {
    const blocked = useSyncExternalStore(
        noopSubscribe,
        notificationsBlocked,
        () => false,
    );

    if (!blocked) {
        return null;
    }

    return (
        <p className="text-center text-xs text-status-warning">
            As notificações estão bloqueadas neste navegador: o fim do ciclo
            avisa pelo som e pelo título da aba.
        </p>
    );
}

function CompleteSessionDialog({
    plan,
    session,
    open,
    onOpenChange,
    askForDetails,
}: {
    plan: StudyPlanSummary;
    session: FocusSession;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    askForDetails: boolean;
}) {
    const form = useForm({
        subject_id: session.subjectId ? String(session.subjectId) : '',
        topic_id: session.topicId ? String(session.topicId) : '',
        activity_type: session.activityType ?? ('exercises' as ActivityType),
        exercises_total: '',
        exercises_correct: '',
        pages_read: '',
    });

    const subject = plan.subjects.find(
        (s) => String(s.id) === form.data.subject_id,
    );

    function submit(event?: FormEvent) {
        event?.preventDefault();

        form.transform((data) => ({
            subject_id: data.subject_id || null,
            topic_id: data.topic_id || null,
            activity_type: data.activity_type,
            exercises_total: data.exercises_total || null,
            exercises_correct: data.exercises_correct || null,
            pages_read: data.pages_read || null,
        }));

        form.post(completeSession().url, { preserveScroll: true });
    }

    // Com "registrar sessão ao encerrar" desligado, encerrar é um clique
    // só: o formulário nunca aparece e o que já estava na sessão é o que
    // fica registrado.
    if (!askForDetails) {
        return (
            <Button
                variant="secondary"
                disabled={form.processing}
                onClick={() =>
                    router.post(
                        completeSession().url,
                        {},
                        { preserveScroll: true },
                    )
                }
            >
                Encerrar sessão
            </Button>
        );
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogTrigger asChild>
                <Button variant="secondary">Encerrar sessão</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Registrar a sessão</DialogTitle>
                </DialogHeader>
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
                                form.setData(
                                    'activity_type',
                                    value as ActivityType,
                                )
                            }
                        />
                        <InputError message={form.errors.activity_type} />
                    </div>

                    <div className="grid grid-cols-3 gap-4">
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
                            <InputError
                                message={form.errors.exercises_correct}
                            />
                        </div>

                        {/* Páginas lidas ao lado das questões: as duas
                            medem o mesmo avanço por ângulos diferentes —
                            uma diz o quanto rendeu, a outra o quanto
                            andou. */}
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="pages_read">Páginas lidas</Label>
                            <Input
                                id="pages_read"
                                type="number"
                                min={0}
                                value={form.data.pages_read}
                                onChange={(event) =>
                                    form.setData(
                                        'pages_read',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.pages_read} />
                        </div>
                    </div>

                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        Encerrar e registrar
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
