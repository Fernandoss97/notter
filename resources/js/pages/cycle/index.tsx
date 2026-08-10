import { Head, Link, router, useForm } from '@inertiajs/react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDurationMinutes } from '@/lib/format-duration';
import { SUBJECT_MODE_LABEL } from '@/lib/study-labels';
import { cn } from '@/lib/utils';
import { index } from '@/routes/cycle';
import { complete as completeBlock } from '@/routes/cycle-blocks';
import {
    destroy as destroySlot,
    reorder as reorderSlots,
    store as storeSlot,
    trim as trimCycle,
    update as updateSlot,
} from '@/routes/cycle-slots';
import { show as showFocus } from '@/routes/focus';
import { index as planIndex } from '@/routes/plans';
import { mode as updateSubjectMode } from '@/routes/subjects';
import type {
    CycleSlotSummary,
    PlanSummary,
    StudyCycle,
    SubjectMode,
} from '@/types/study';

/** Acima disto a tela avisa, mas não impede — ver CycleSlot::MAX. */
const RECOMMENDED_MAX_BLOCK_MINUTES = 90;

interface CycleIndexProps {
    plans: PlanSummary[];
    plan: { id: number; name: string } | null;
    cycle: StudyCycle | null;
    availableSubjects: { id: number; name: string }[];
}

export default function CycleIndex({
    plans,
    plan,
    cycle,
    availableSubjects,
}: CycleIndexProps) {
    const [busy, setBusy] = useState(false);

    if (!plan || !cycle) {
        return (
            <>
                <Head title="Ciclo de estudos" />
                <Page maxWidth="narrow">
                    <NoPlanPanel />
                </Page>
            </>
        );
    }

    const active = cycle.slots.filter((slot) => slot.state === 'active');
    const done = active.filter((slot) => slot.block?.completed).length;
    const switchingClassName = cn(
        'transition-opacity duration-base ease-out',
        busy && 'opacity-50',
    );
    const crowded = cycle.readingCount > cycle.recommendedReading;

    return (
        <>
            <Head title="Ciclo de estudos" />
            <Page maxWidth="narrow">
                <div aria-busy={busy} className={switchingClassName}>
                    <PageHeader
                        title="Ciclo de estudos"
                        subtitle={
                            active.length === 0
                                ? `${plan.name} · nenhuma disciplina no rodízio`
                                : `${plan.name} · volta ${cycle.lap} · ${done} de ${active.length} blocos`
                        }
                        actions={
                            <>
                                <TrimDialog activeCount={active.length} />
                                <Button variant="outline" asChild>
                                    <Link href={showFocus()}>
                                        Ir para o foco
                                    </Link>
                                </Button>
                            </>
                        }
                    />
                </div>

                <CrowdedHint
                    visible={crowded}
                    reading={cycle.readingCount}
                    recommended={cycle.recommendedReading}
                />

                <PlanSwitcher
                    plans={plans}
                    activePlanId={plan.id}
                    onBusyChange={setBusy}
                />

                <div
                    aria-busy={busy}
                    className={cn('flex flex-col gap-1.5', switchingClassName)}
                    key={plan.id}
                >
                    <Panel title={`Volta ${cycle.lap}`} pad={false}>
                        <div className="stagger-children flex flex-col gap-1.5 p-3">
                            {cycle.slots.map((slot, position) => (
                                <SlotRow
                                    key={slot.id}
                                    slot={slot}
                                    position={position}
                                    slots={cycle.slots}
                                />
                            ))}
                            <EmptyCycle visible={cycle.slots.length === 0} />
                        </div>
                    </Panel>
                </div>

                <AddSlotPanel subjects={availableSubjects} />
            </Page>
        </>
    );
}

CycleIndex.layout = {
    breadcrumbs: [{ title: 'Ciclo de estudos', href: index() }],
};

function NoPlanPanel() {
    return (
        <Panel title="Ciclo de estudos">
            <p className="text-sm text-fg-2">
                O ciclo é o rodízio das disciplinas do plano ativo — quem vem
                agora, e por quanto tempo. Crie um plano de estudos para montá-lo.
            </p>
            <Button asChild className="w-fit">
                <Link href={planIndex()}>Criar plano de estudos</Link>
            </Button>
        </Panel>
    );
}

/**
 * O sinal de ciclo inchado, antes de a volta ficar longa demais: teoria
 * nova é o que mais cansa, e é dela que o método pede para não exagerar.
 * Fica montado e escondido para não empurrar a lista ao aparecer.
 */
function CrowdedHint({
    visible,
    reading,
    recommended,
}: {
    visible: boolean;
    reading: number;
    recommended: number;
}) {
    return (
        <p
            data-slot="cycle-crowded"
            data-visible={visible}
            aria-hidden={!visible}
            inert={!visible || undefined}
            hidden={!visible}
            className={cn(
                'rounded-md border border-status-warning/30 bg-status-warning/10 px-4 py-3 text-sm text-status-warning',
                !visible && 'hidden',
            )}
        >
            {reading} disciplinas em leitura ao mesmo tempo — o recomendado é
            até {recommended}. Passe para revisão as que já tiveram a teoria
            vista, ou enxugue o ciclo para nivelar antes de seguir.
        </p>
    );
}

/**
 * Enxugar é o nivelamento do método: reduzir temporariamente o número de
 * matérias para mergulhar nas mais importantes. Quem fica são as primeiras
 * da fila — a ordem do rodízio já diz o que importa mais, e pedir a mesma
 * informação de novo seria fazer o usuário se repetir.
 */
function TrimDialog({ activeCount }: { activeCount: number }) {
    const [open, setOpen] = useState(false);
    const [keep, setKeep] = useState('4');

    if (activeCount < 3) {
        return null;
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        router.post(
            trimCycle().url,
            { keep: Number(keep) },
            { preserveScroll: true, onSuccess: () => setOpen(false) },
        );
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">Enxugar ciclo</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Enxugar o ciclo</DialogTitle>
                    <DialogDescription>
                        As disciplinas que saírem ficam fora do rodízio, não
                        apagadas: trilha, revisões e tempo estudado continuam
                        onde estão, e elas voltam quando você quiser. Ficam as
                        primeiras da fila.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="trim_keep">
                            Quantas disciplinas manter
                        </Label>
                        <Input
                            id="trim_keep"
                            type="number"
                            min={2}
                            max={activeCount}
                            value={keep}
                            onChange={(event) => setKeep(event.target.value)}
                            className="w-24"
                        />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button type="submit">Enxugar</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Fica montado e some via `hidden`, como o estado vazio da trilha: é o
 * último filho da lista, então escondê-lo não move nada acima dele.
 */
function EmptyCycle({ visible }: { visible: boolean }) {
    return (
        <p
            data-slot="cycle-empty"
            data-visible={visible}
            aria-hidden={!visible}
            inert={!visible || undefined}
            hidden={!visible}
            className={cn('p-2 text-sm text-fg-2', !visible && 'hidden')}
        >
            Nenhuma disciplina no rodízio ainda. Comece com 4 a 6 delas e
            alterne áreas diferentes — duas matérias parecidas em sequência
            cansam mais do que revezar.
        </p>
    );
}

/**
 * Uma cadeira do rodízio. A ordem se muda por botões, não por arrastar:
 * teclado e leitor de tela vêm antes, e o ganho de um drag numa lista de
 * meia dúzia de itens não paga a dependência.
 */
function SlotRow({
    slot,
    position,
    slots,
}: {
    slot: CycleSlotSummary;
    position: number;
    slots: CycleSlotSummary[];
}) {
    const paused = slot.state === 'paused';
    const block = slot.block;

    function move(direction: -1 | 1) {
        const target = position + direction;

        if (target < 0 || target >= slots.length) {
            return;
        }

        const order = slots.map((item) => item.id);
        [order[position], order[target]] = [order[target], order[position]];

        router.post(
            reorderSlots().url,
            { slots: order },
            { preserveScroll: true, showProgress: false },
        );
    }

    return (
        <div
            data-slot="cycle-slot"
            data-current={slot.isCurrent || undefined}
            className={cn(
                'flex items-center gap-3 rounded-sm px-2 py-2 transition-colors duration-fast ease-out hover:bg-muted',
                slot.isCurrent && 'bg-muted',
                paused && 'opacity-60',
            )}
        >
            <div className="flex flex-col">
                <ReorderButton
                    label={`Mover ${slot.subjectName} para cima`}
                    disabled={position === 0}
                    onClick={() => move(-1)}
                >
                    ▲
                </ReorderButton>
                <ReorderButton
                    label={`Mover ${slot.subjectName} para baixo`}
                    disabled={position === slots.length - 1}
                    onClick={() => move(1)}
                >
                    ▼
                </ReorderButton>
            </div>

            <span className="w-6 shrink-0 text-right text-xs text-muted-foreground tabular-nums">
                {position + 1}
            </span>

            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <div className="flex items-center gap-2">
                    <span className="truncate text-sm text-foreground">
                        {slot.subjectName}
                    </span>
                    <StatusBadge slot={slot} />
                    {slot.boosted && <Badge tone="focus">Imersão</Badge>}
                </div>
                <span className="text-xs text-muted-foreground">
                    {block
                        ? `${formatDurationMinutes(Math.floor(block.elapsedSeconds / 60))} de ${formatDurationMinutes(block.targetMinutes)}`
                        : `${formatDurationMinutes(slot.blockMinutes)} por bloco · fora do rodízio`}
                </span>
            </div>

            {block && (
                <div className="w-32 shrink-0">
                    <ProgressBar
                        value={block.progressPercent}
                        color={block.completed ? 'success' : 'focus'}
                    />
                </div>
            )}

            <div className="flex shrink-0 items-center gap-1">
                <ModeSelect slot={slot} />
                {slot.isCurrent && (
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={showFocus()}>Estudar</Link>
                    </Button>
                )}
                {slot.isCurrent && block && !block.completed && (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            router.post(
                                completeBlock(block.id).url,
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        Encerrar
                    </Button>
                )}
                <SlotSettings slot={slot} />
            </div>
        </div>
    );
}

function ReorderButton({
    label,
    disabled,
    onClick,
    children,
}: {
    label: string;
    disabled: boolean;
    onClick: () => void;
    children: string;
}) {
    return (
        <button
            type="button"
            aria-label={label}
            disabled={disabled}
            onClick={onClick}
            className={cn(
                'flex h-3.5 w-4 items-center justify-center rounded-[3px] text-[8px] text-muted-foreground',
                'transition-[color,background-color,scale] duration-fast ease-out',
                'outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                'hover:bg-border-strong hover:text-foreground',
                'active:scale-90 active:duration-instant',
                'disabled:pointer-events-none disabled:opacity-30',
            )}
        >
            {children}
        </button>
    );
}

/**
 * O pé em que a disciplina está. Mudar para revisão encolhe o bloco dela
 * no rodízio, e encerrar a tira de cena — o servidor cuida disso, para não
 * virar uma segunda edição que o usuário teria de lembrar de fazer.
 */
function ModeSelect({ slot }: { slot: CycleSlotSummary }) {
    const [pending, setPending] = useState<SubjectMode | null>(null);
    const mode = pending ?? slot.subjectMode;

    function change(next: string) {
        setPending(next as SubjectMode);

        router.put(
            updateSubjectMode(slot.subjectId).url,
            { mode: next },
            {
                preserveScroll: true,
                showProgress: false,
                onFinish: () => setPending(null),
            },
        );
    }

    return (
        <Select value={mode} onValueChange={change}>
            <SelectTrigger
                size="sm"
                aria-label={`Situação de ${slot.subjectName}`}
                className="w-28"
            >
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {(
                    Object.entries(SUBJECT_MODE_LABEL) as [
                        SubjectMode,
                        string,
                    ][]
                ).map(([value, label]) => (
                    <SelectItem key={value} value={value}>
                        {label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function StatusBadge({ slot }: { slot: CycleSlotSummary }) {
    if (slot.state === 'paused') {
        return <Badge tone="muted">Fora do rodízio</Badge>;
    }

    if (slot.block?.completed) {
        return <Badge tone="success">Concluído</Badge>;
    }

    if (slot.isCurrent) {
        return <Badge tone="focus">É a vez</Badge>;
    }

    return null;
}

function Badge({
    tone,
    children,
}: {
    tone: 'muted' | 'success' | 'focus';
    children: string;
}) {
    return (
        <span
            className={cn(
                'shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold tracking-wide uppercase',
                tone === 'muted' && 'bg-muted text-muted-foreground',
                tone === 'success' && 'bg-status-success/15 text-status-success',
                tone === 'focus' && 'bg-focus-500/15 text-focus-500',
            )}
        >
            {children}
        </span>
    );
}

/**
 * Duração do bloco e entrada/saída do rodízio. A duração nova só vale para
 * as próximas voltas — o bloco aberto congelou a sua quando abriu.
 */
function SlotSettings({ slot }: { slot: CycleSlotSummary }) {
    const [editing, setEditing] = useState(false);
    const [minutes, setMinutes] = useState(String(slot.blockMinutes));
    const paused = slot.state === 'paused';

    function save(event: FormEvent) {
        event.preventDefault();

        router.put(
            updateSlot(slot.id).url,
            { block_minutes: Number(minutes) },
            { preserveScroll: true, onSuccess: () => setEditing(false) },
        );
    }

    function toggleState() {
        router.put(
            updateSlot(slot.id).url,
            { state: paused ? 'active' : 'paused' },
            { preserveScroll: true },
        );
    }

    if (editing) {
        return (
            <form onSubmit={save} className="flex items-center gap-1">
                <Input
                    aria-label={`Minutos por bloco de ${slot.subjectName}`}
                    type="number"
                    min={60}
                    max={120}
                    step={5}
                    value={minutes}
                    onChange={(event) => setMinutes(event.target.value)}
                    className="h-8 w-20"
                />
                <Button type="submit" size="sm">
                    Salvar
                </Button>
            </form>
        );
    }

    return (
        <>
            <Button
                variant="ghost"
                size="sm"
                aria-label={`Ajustar o bloco de ${slot.subjectName}`}
                onClick={() => setEditing(true)}
            >
                {slot.blockMinutes}min
            </Button>
            <Button
                variant="ghost"
                size="sm"
                aria-label={
                    paused
                        ? `Devolver ${slot.subjectName} ao rodízio`
                        : `Tirar ${slot.subjectName} do rodízio`
                }
                onClick={toggleState}
            >
                {paused ? 'Voltar' : 'Pausar'}
            </Button>
            <Button
                variant="ghost"
                size="sm"
                aria-label={`Remover ${slot.subjectName} do ciclo`}
                onClick={() =>
                    router.delete(destroySlot(slot.id).url, {
                        preserveScroll: true,
                    })
                }
            >
                Remover
            </Button>
        </>
    );
}

function AddSlotPanel({ subjects }: { subjects: { id: number; name: string }[] }) {
    const form = useForm({
        subject_id: '',
        block_minutes: '60',
        boost: false,
    });
    const overRecommended =
        Number(form.data.block_minutes) > RECOMMENDED_MAX_BLOCK_MINUTES;

    function submit(event: FormEvent) {
        event.preventDefault();

        form.transform((data) => ({
            subject_id: Number(data.subject_id),
            block_minutes: Number(data.block_minutes),
            boost: data.boost,
        }));

        form.post(storeSlot().url, {
            preserveScroll: true,
            onSuccess: () => form.setData('subject_id', ''),
        });
    }

    return (
        <Panel title="Adicionar disciplina ao ciclo">
            {subjects.length === 0 ? (
                <p className="text-sm text-fg-2">
                    Todas as disciplinas do plano já estão no ciclo. Cadastre
                    outra no plano para acrescentá-la ao rodízio.
                </p>
            ) : (
                <form
                    onSubmit={submit}
                    className="flex flex-wrap items-end gap-4"
                >
                    <div className="flex min-w-[200px] flex-1 flex-col gap-2">
                        <Label htmlFor="cycle_subject">Disciplina</Label>
                        <Select
                            value={form.data.subject_id}
                            onValueChange={(value) =>
                                form.setData('subject_id', value)
                            }
                        >
                            <SelectTrigger
                                id="cycle_subject"
                                className="w-full"
                            >
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

                    <div className="flex w-36 flex-col gap-2">
                        <Label htmlFor="cycle_block_minutes">
                            Minutos por bloco
                        </Label>
                        <Input
                            id="cycle_block_minutes"
                            type="number"
                            min={60}
                            max={120}
                            step={5}
                            value={form.data.block_minutes}
                            onChange={(event) =>
                                form.setData('block_minutes', event.target.value)
                            }
                        />
                        {form.errors.block_minutes && (
                            <p className="text-sm text-destructive">
                                {form.errors.block_minutes}
                            </p>
                        )}
                    </div>

                    <label className="flex items-center gap-2 pb-2 text-sm text-fg-2">
                        <input
                            type="checkbox"
                            checked={form.data.boost}
                            onChange={(event) =>
                                form.setData('boost', event.target.checked)
                            }
                            className="size-4 rounded-[4px] border-border-strong accent-focus-500"
                        />
                        Imersão inicial
                    </label>

                    <Button type="submit" disabled={form.processing}>
                        Adicionar
                    </Button>

                    <p
                        data-slot="cycle-boost-hint"
                        aria-hidden={!form.data.boost}
                        inert={!form.data.boost || undefined}
                        hidden={!form.data.boost}
                        className={cn(
                            'w-full text-xs text-muted-foreground',
                            !form.data.boost && 'hidden',
                        )}
                    >
                        Matéria nova entra com bloco maior por duas semanas, e
                        depois volta a valer o que todas valem — o reforço tem
                        prazo para não virar uma prioridade eterna.
                    </p>

                    <p
                        data-slot="cycle-block-warning"
                        aria-hidden={!overRecommended}
                        inert={!overRecommended || undefined}
                        hidden={!overRecommended}
                        className={cn(
                            'w-full text-xs text-status-warning',
                            !overRecommended && 'hidden',
                        )}
                    >
                        Blocos acima de 90 minutos são a exceção, não a regra —
                        valem para matérias densas como Contabilidade ou
                        Estatística.
                    </p>
                </form>
            )}
        </Panel>
    );
}
