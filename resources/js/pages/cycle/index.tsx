import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronDownIcon, ChevronUpIcon } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
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
import { findAreaClashes } from '@/lib/cycle-interleaving';
import { formatDurationMinutes } from '@/lib/format-duration';
import { SUBJECT_AREA_LABEL, SUBJECT_MODE_LABEL } from '@/lib/study-labels';
import { cn } from '@/lib/utils';
import { index } from '@/routes/cycle';
import { complete as completeBlock } from '@/routes/cycle-blocks';
import {
    destroy as destroySlot,
    interleave as interleaveSlots,
    reorder as reorderSlots,
    store as storeSlot,
    trim as trimCycle,
    update as updateSlot,
} from '@/routes/cycle-slots';
import { show as showFocus } from '@/routes/focus';
import { index as planIndex } from '@/routes/plans';
import { update as updateSubject } from '@/routes/subjects';
import type { CycleSlotSummary, PlanSummary, StudyCycle } from '@/types/study';

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
    const clashes = findAreaClashes(cycle.slots);

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
                                <InterleaveButton visible={active.length > 2} />
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

                <ClashHint clashes={clashes} />

                <CoreHint
                    visible={
                        active.length > 0 &&
                        active.length < cycle.recommendedCore
                    }
                    active={active.length}
                    recommended={cycle.recommendedCore}
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
                        <div className="stagger-children flex flex-col gap-2 p-4">
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
                agora, e por quanto tempo. Crie um plano de estudos para
                montá-lo.
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
 * Duas matérias da mesma família em sequência. Aviso, nunca impedimento:
 * há motivo legítimo para dois direitos seguidos, e uma restrição rígida
 * aqui só faria o usuário brigar com a tela.
 */
function ClashHint({ clashes }: { clashes: string[] }) {
    const visible = clashes.length > 0;

    return (
        <p
            data-slot="cycle-clash"
            data-visible={visible}
            aria-hidden={!visible}
            inert={!visible || undefined}
            hidden={!visible}
            className={cn('text-sm text-fg-2', !visible && 'hidden')}
        >
            {clashes.join('; ')}{' '}
            {clashes.length === 1
                ? 'são da mesma área'
                : 'são das mesmas áreas'}{' '}
            e estão em sequência. Alternar áreas diferentes descansa a
            concentração — "Intercalar áreas" reorganiza a fila.
        </p>
    );
}

/**
 * O núcleo básico sugerido pela carga horária (§2). Só aparece quando o
 * ciclo está abaixo dele: quem já tem matérias demais recebe o outro aviso,
 * e dois conselhos contrários na mesma tela não ajudam ninguém.
 */
function CoreHint({
    visible,
    active,
    recommended,
}: {
    visible: boolean;
    active: number;
    recommended: number;
}) {
    return (
        <p
            data-slot="cycle-core"
            data-visible={visible}
            aria-hidden={!visible}
            inert={!visible || undefined}
            hidden={!visible}
            className={cn('text-sm text-fg-2', !visible && 'hidden')}
        >
            Para a sua carga horária, o método sugere começar com {recommended}{' '}
            disciplinas — você tem {active}. É sugestão, não regra: o ciclo
            funciona com o que couber na sua semana.
        </p>
    );
}

/**
 * Reorganiza a fila alternando as famílias. Fica escondido enquanto o
 * rodízio é pequeno demais para haver o que intercalar, em vez de sumir:
 * assim montar o ciclo não faz os botões do cabeçalho dançarem.
 */
function InterleaveButton({ visible }: { visible: boolean }) {
    return (
        <span
            data-slot="cycle-interleave"
            aria-hidden={!visible}
            inert={!visible || undefined}
            hidden={!visible}
            className={cn(!visible && 'hidden')}
        >
            <Button
                variant="outline"
                onClick={() =>
                    router.post(
                        interleaveSlots().url,
                        {},
                        { preserveScroll: true },
                    )
                }
            >
                Intercalar áreas
            </Button>
        </span>
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
 *
 * A linha quebra em vez de espremer. Com a área, a situação e cinco ações
 * lado a lado, a coluna do nome era achatada a zero e a disciplina — a
 * única informação que importa aqui — sumia da tela.
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
                // Grade de duas colunas em vez de um recuo calculado à mão:
                // os controles caem sob o nome porque compartilham a coluna
                // com ele, e nada quebra quando a coluna das setas muda de
                // largura.
                //
                // `minmax(0,1fr)`, não `1fr`: uma trilha `1fr` não encolhe
                // abaixo do conteúdo, e a faixa de controles empurrava a
                // linha para fora do painel — a página ganhava rolagem
                // horizontal e nunca parava de se reacomodar.
                'grid grid-cols-[auto_minmax(0,1fr)] items-center gap-x-3 gap-y-2 rounded-sm px-3 py-3 transition-colors duration-fast ease-out hover:bg-muted',
                slot.isCurrent && 'bg-muted',
                paused && 'opacity-60',
            )}
        >
            <div className="flex items-center gap-2">
                <div className="flex flex-col gap-0.5">
                    <ReorderButton
                        label={`Mover ${slot.subjectName} para cima`}
                        disabled={position === 0}
                        onClick={() => move(-1)}
                    >
                        <ChevronUpIcon className="size-4" />
                    </ReorderButton>
                    <ReorderButton
                        label={`Mover ${slot.subjectName} para baixo`}
                        disabled={position === slots.length - 1}
                        onClick={() => move(1)}
                    >
                        <ChevronDownIcon className="size-4" />
                    </ReorderButton>
                </div>

                <span className="w-5 shrink-0 text-right text-sm text-muted-foreground tabular-nums">
                    {position + 1}
                </span>
            </div>

            <div className="flex min-w-0 items-center gap-3">
                <div className="flex min-w-0 flex-1 flex-col gap-1">
                    <div className="flex items-center gap-2">
                        <span className="truncate text-md font-semibold text-foreground">
                            {slot.subjectName}
                        </span>
                        <StatusBadge slot={slot} />
                        {slot.boosted && <Badge tone="focus">Imersão</Badge>}
                    </div>
                    <span className="text-sm text-muted-foreground">
                        {block
                            ? `${formatDurationMinutes(Math.floor(block.elapsedSeconds / 60))} de ${formatDurationMinutes(block.targetMinutes)}`
                            : `${formatDurationMinutes(slot.effectiveBlockMinutes)} por bloco · fora do rodízio`}
                        {slot.boosted &&
                            ` · ${slot.blockMinutes}min quando a imersão acabar`}
                    </span>
                </div>

                {block && (
                    <div className="w-36 shrink-0">
                        <ProgressBar
                            value={block.progressPercent}
                            color={block.completed ? 'success' : 'focus'}
                        />
                    </div>
                )}
            </div>

            {/* Os controles ficam alinhados sob o nome. Deixá-los disputar
                a primeira faixa achatava a coluna do nome — a única
                informação que a linha existe para dar. */}
            <div className="col-start-2 flex min-w-0 flex-wrap items-center gap-2">
                <SubjectFieldSelect
                    slot={slot}
                    field="area"
                    value={slot.subjectArea}
                    labels={SUBJECT_AREA_LABEL}
                    label={`Área de ${slot.subjectName}`}
                    width="w-36"
                />
                <SubjectFieldSelect
                    slot={slot}
                    field="mode"
                    value={slot.subjectMode}
                    labels={SUBJECT_MODE_LABEL}
                    label={`Situação de ${slot.subjectName}`}
                    width="w-32"
                />
                {slot.isCurrent && (
                    <Button variant="ghost" asChild>
                        <Link href={showFocus()}>Estudar</Link>
                    </Button>
                )}
                {slot.isCurrent && block && !block.completed && (
                    <Button
                        variant="ghost"
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

/**
 * Alvo de 24px, o mínimo do WCAG 2.5.8: os 14x16px de antes eram menores
 * que o próprio ponteiro do mouse.
 */
function ReorderButton({
    label,
    disabled,
    onClick,
    children,
}: {
    label: string;
    disabled: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            aria-label={label}
            disabled={disabled}
            onClick={onClick}
            className={cn(
                'flex size-6 items-center justify-center rounded-sm text-muted-foreground',
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
 * Um campo da disciplina editado direto da linha do rodízio.
 *
 * Otimista com estado local: a resposta troca a tela inteira, e sem o eco
 * o seletor voltaria ao valor antigo por um frame.
 */
function SubjectFieldSelect<T extends string>({
    slot,
    field,
    value,
    labels,
    label,
    width,
}: {
    slot: CycleSlotSummary;
    field: 'mode' | 'area';
    value: T;
    labels: Record<T, string>;
    label: string;
    width: string;
}) {
    const [pending, setPending] = useState<T | null>(null);
    const current = pending ?? value;

    function change(next: string) {
        setPending(next as T);

        router.put(
            updateSubject(slot.subjectId).url,
            { [field]: next },
            {
                preserveScroll: true,
                showProgress: false,
                onFinish: () => setPending(null),
            },
        );
    }

    return (
        <Select value={current} onValueChange={change}>
            <SelectTrigger aria-label={label} className={width}>
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {(Object.entries(labels) as [T, string][]).map(
                    ([option, optionLabel]) => (
                        <SelectItem key={option} value={option}>
                            {optionLabel}
                        </SelectItem>
                    ),
                )}
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
                'shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold tracking-wide uppercase',
                tone === 'muted' && 'bg-muted text-muted-foreground',
                tone === 'success' &&
                    'bg-status-success/15 text-status-success',
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
                    className="w-24"
                />
                <Button type="submit">Salvar</Button>
            </form>
        );
    }

    return (
        <>
            <Button
                variant="ghost"
                aria-label={`Ajustar o bloco de ${slot.subjectName}`}
                onClick={() => setEditing(true)}
            >
                {slot.blockMinutes}min
            </Button>
            <Button
                variant="ghost"
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

function AddSlotPanel({
    subjects,
}: {
    subjects: { id: number; name: string }[];
}) {
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
                                form.setData(
                                    'block_minutes',
                                    event.target.value,
                                )
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
                            className="size-5 rounded-[4px] border-border-strong accent-focus-500"
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
                        Matéria nova ganha 30 minutos a mais por bloco durante
                        duas semanas. O reforço é somado por cima da duração
                        escolhida e some sozinho no fim do prazo — assim ele não
                        vira uma prioridade eterna que ninguém lembra de
                        desfazer.
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
