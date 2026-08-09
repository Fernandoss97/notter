import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Dot } from '@/components/dot';
import { Button } from '@/components/ui/button';
import { ProgressBar } from '@/components/ui/progress-bar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useHiddenTime } from '@/hooks/use-hidden-time';
import { useLiveElapsedSeconds } from '@/hooks/use-live-elapsed-seconds';
import { usePhaseAlert } from '@/hooks/use-phase-alert';
import { usePhaseEnd } from '@/hooks/use-phase-end';
import { useTitleFlash } from '@/hooks/use-title-flash';
import { prepareAlerts } from '@/lib/focus-alerts';
import { formatClock } from '@/lib/format-duration';
import { phaseEndTitle } from '@/lib/phase-alert';
import { cn } from '@/lib/utils';
import {
    pause as pauseSession,
    resume as resumeSession,
    show as showFocus,
} from '@/routes/focus';

export function SessionBar() {
    const { activeSession } = usePage().props;
    const elapsedSeconds = useLiveElapsedSeconds(activeSession);
    const { isCurrentUrl } = useCurrentUrl();
    const [pendingPaused, setPendingPaused] = useState<boolean | null>(null);
    // A barra fecha o ciclo de qualquer tela — é a única coisa montada em
    // todas elas.
    const finished = usePhaseEnd(activeSession, elapsedSeconds);
    const alert = usePhaseAlert(activeSession?.state);
    // Esconder o tempo no cronômetro e deixá-lo aqui não esconderia nada:
    // a barra persegue o usuário por todas as telas.
    const [hiddenTime] = useHiddenTime();

    // O título da aba é o canal que atravessa a aba em segundo plano sem
    // depender de permissão nenhuma.
    useTitleFlash(alert ? `⏰ ${phaseEndTitle(alert.phase)}` : null);

    // O invólucro fica sempre montado, mesmo sem sessão: é o que permite
    // animar a altura de 0 -> 64px em vez de o conteúdo da página saltar.
    const visible = Boolean(activeSession) && !isCurrentUrl(showFocus());

    // A cor acompanha a *fase*: um intervalo em andamento é verde-água
    // mesmo rodando, e o vermelho de foco não volta antes da hora.
    const onBreak = activeSession ? activeSession.phase !== 'focus' : false;
    const paused = pendingPaused ?? activeSession?.state === 'paused';
    const calm = onBreak || paused;
    // Regressivo, como o cronômetro da tela de foco: os dois mostram o
    // mesmo ciclo e não podem discordar sobre o que o número significa.
    const remainingSeconds = activeSession
        ? Math.max(0, activeSession.plannedSeconds - elapsedSeconds)
        : 0;
    const progress = activeSession
        ? Math.min(
              100,
              Math.round((elapsedSeconds / activeSession.plannedSeconds) * 100),
          )
        : 0;

    function toggle() {
        // Retomar é um gesto do usuário — a única janela em que o
        // navegador libera o áudio e a permissão de notificação.
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

    return (
        <div
            data-slot="session-bar"
            data-visible={visible}
            // Exceção deliberada a "só transform/opacity": reservar e devolver
            // os 64px do fluxo *é* uma mudança de layout — animá-la é o que
            // evita o pulo. Só o container paga o custo, uma vez por transição.
            className="sticky top-0 z-10 h-0 overflow-hidden transition-[height] duration-base ease-out data-[visible=true]:h-16"
        >
            {activeSession && (
                <div
                    aria-hidden={!visible}
                    // `|| undefined` para o atributo sumir de vez quando
                    // visível: `inert="false"` ainda inerta no DOM.
                    inert={!visible || undefined}
                    className={cn(
                        'flex h-16 items-center gap-5 border-b px-10',
                        'transition-[background-color,border-color,opacity,translate] duration-base ease-out',
                        visible ? 'opacity-100' : '-translate-y-2 opacity-0',
                        calm
                            ? 'border-pausa-200 bg-pausa-100'
                            : 'border-focus-200 bg-focus-100',
                    )}
                >
                    <span
                        className={cn(
                            'flex items-center gap-2 text-sm font-bold tracking-wide uppercase',
                            'transition-colors duration-base ease-out',
                            calm ? 'text-pausa-600' : 'text-focus-500',
                        )}
                    >
                        <Dot
                            className={cn(
                                'size-2.5 transition-colors duration-base ease-out',
                                calm ? 'bg-pausa-500' : 'bg-focus-500',
                                (alert || finished) && 'animate-pulse',
                            )}
                        />
                        {alert
                            ? phaseEndTitle(alert.phase)
                            : finished
                              ? phaseEndTitle(activeSession.phase)
                              : paused
                                ? 'Pausado'
                                : onBreak
                                  ? 'Intervalo'
                                  : 'Em foco'}
                    </span>
                    {!hiddenTime && (
                        <span
                            role="timer"
                            aria-label={`Tempo restante: ${formatClock(remainingSeconds)}`}
                            className="font-mono text-2xl font-semibold text-foreground tabular-nums"
                        >
                            {formatClock(remainingSeconds)}
                        </span>
                    )}
                    <span className="text-base text-fg-2">
                        {activeSession.subject} · {activeSession.topic}
                    </span>
                    <ProgressBar
                        value={progress}
                        color={calm ? 'pausa' : 'focus'}
                        height={6}
                        className="max-w-[320px] flex-1"
                        trackClassName="bg-foreground/10"
                    />
                    <div className="flex-1" />
                    {/* Com o tempo esgotado não há o que pausar: o ciclo
                        acabou e o avanço já foi pedido ao servidor. Sai do
                        DOM em vez de ficar escondido — um botão invisível
                        e clicável é pior que botão nenhum. */}
                    {!finished && (
                        <Button
                            variant="ghost"
                            onClick={toggle}
                            className={cn(
                                'font-semibold',
                                calm
                                    ? 'text-pausa-600 hover:bg-pausa-200 hover:text-pausa-600'
                                    : 'text-focus-500 hover:bg-focus-200 hover:text-focus-500',
                            )}
                        >
                            {paused ? 'Retomar' : 'Pausar'}
                        </Button>
                    )}
                    <Button
                        asChild
                        variant="outline"
                        className={cn(
                            'bg-transparent font-semibold',
                            calm
                                ? 'border-pausa-500 text-pausa-600 hover:bg-pausa-200 hover:text-pausa-600'
                                : 'border-focus-500 text-focus-500 hover:bg-focus-200 hover:text-focus-500',
                        )}
                    >
                        <Link href={showFocus()}>Abrir sessão</Link>
                    </Button>
                </div>
            )}
        </div>
    );
}
