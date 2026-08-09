import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { SegmentedControl } from '@/components/ui/segmented-control';
import { Spinner } from '@/components/ui/spinner';
import { activate } from '@/routes/plans';
import type { PlanSummary } from '@/types/study';

/**
 * Trocar de plano costuma responder em menos de 100ms. Esmaecer a tela
 * nesse tempo só produz um piscar — o indicador de espera espera para ver
 * se a espera é mesmo perceptível.
 */
const BUSY_DELAY_MS = 180;

/**
 * O seletor do plano ativo, compartilhado pelas telas que mostram um plano
 * inteiro. Trocar aqui ativa o plano de verdade: foco, trilha, plano e
 * progresso falam todos do mesmo, e um "plano em exibição" separado do
 * "plano ativo" seria um segundo conceito para o usuário administrar.
 *
 * Só aparece quando há mais de um plano — um seletor de uma opção é ruído.
 *
 * `onBusyChange` deixa a tela em volta esmaecer enquanto o conteúdo novo
 * não chega, porque é ela, não este componente, que sabe o que substituir.
 */
export function PlanSwitcher({
    plans,
    activePlanId,
    onBusyChange,
}: {
    plans: PlanSummary[];
    activePlanId: number;
    onBusyChange?: (busy: boolean) => void;
}) {
    const [pendingPlanId, setPendingPlanId] = useState<number | null>(null);
    const [busy, setBusy] = useState(false);
    const busyTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const latestSwitch = useRef<number | null>(null);

    function clearBusyTimer() {
        if (busyTimer.current !== null) {
            clearTimeout(busyTimer.current);
            busyTimer.current = null;
        }
    }

    useEffect(() => clearBusyTimer, []);

    function announceBusy(next: boolean) {
        setBusy(next);
        onBusyChange?.(next);
    }

    function switchPlan(planId: number) {
        setPendingPlanId(planId);
        latestSwitch.current = planId;

        if (busyTimer.current === null) {
            busyTimer.current = setTimeout(
                () => announceBusy(true),
                BUSY_DELAY_MS,
            );
        }

        router.post(
            activate(planId).url,
            {},
            {
                preserveScroll: true,
                showProgress: false,
                onFinish: () => {
                    // Uma troca mais nova cancela a anterior; o `onFinish` da
                    // cancelada não pode encerrar a espera de quem a substituiu.
                    if (latestSwitch.current !== planId) {
                        return;
                    }

                    latestSwitch.current = null;
                    clearBusyTimer();
                    announceBusy(false);
                    setPendingPlanId(null);
                },
            },
        );
    }

    if (plans.length <= 1) {
        return null;
    }

    return (
        <div className="flex items-center gap-3">
            <span className="text-xs text-muted-foreground">Plano ativo</span>
            <SegmentedControl
                options={plans.map((plan) => ({
                    value: String(plan.id),
                    label: plan.name,
                }))}
                value={String(pendingPlanId ?? activePlanId)}
                onValueChange={(value) => switchPlan(Number(value))}
            />
            {busy && (
                <Spinner
                    data-slot="plan-switcher-busy"
                    aria-label="Trocando de plano"
                    className="text-muted-foreground"
                />
            )}
        </div>
    );
}
