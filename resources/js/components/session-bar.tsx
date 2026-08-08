import { Link, usePage } from '@inertiajs/react';
import { Dot } from '@/components/dot';
import { Button } from '@/components/ui/button';
import { ProgressBar } from '@/components/ui/progress-bar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useLiveElapsedSeconds } from '@/hooks/use-live-elapsed-seconds';
import { cn } from '@/lib/utils';
import { show as showFocus } from '@/routes/focus';

function formatElapsed(totalSeconds: number): string {
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

export function SessionBar() {
    const { activeSession } = usePage().props;
    const elapsedSeconds = useLiveElapsedSeconds(activeSession);
    const { isCurrentUrl } = useCurrentUrl();

    // O invólucro fica sempre montado, mesmo sem sessão: é o que permite
    // animar a altura de 0 -> 64px em vez de o conteúdo da página saltar.
    const visible = Boolean(activeSession) && !isCurrentUrl(showFocus());

    const paused = activeSession?.state === 'paused';
    const progress = activeSession
        ? Math.min(
              100,
              Math.round((elapsedSeconds / activeSession.plannedSeconds) * 100),
          )
        : 0;

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
                        paused
                            ? 'border-pausa-200 bg-pausa-100'
                            : 'border-focus-200 bg-focus-100',
                    )}
                >
                    <span
                        className={cn(
                            'flex items-center gap-2 text-sm font-bold tracking-wide uppercase',
                            'transition-colors duration-base ease-out',
                            paused ? 'text-pausa-600' : 'text-focus-500',
                        )}
                    >
                        <Dot
                            className={cn(
                                'size-2.5 transition-colors duration-base ease-out',
                                paused ? 'bg-pausa-500' : 'bg-focus-500',
                            )}
                        />
                        {paused ? 'Pausado' : 'Em foco'}
                    </span>
                    <span className="font-mono text-2xl font-semibold text-foreground tabular-nums">
                        {formatElapsed(elapsedSeconds)}
                    </span>
                    <span className="text-base text-fg-2">
                        {activeSession.subject} · {activeSession.topic}
                    </span>
                    <ProgressBar
                        value={progress}
                        color={paused ? 'pausa' : 'focus'}
                        height={6}
                        className="max-w-[320px] flex-1"
                        trackClassName="bg-foreground/10"
                    />
                    <div className="flex-1" />
                    <Button
                        asChild
                        variant="outline"
                        className={cn(
                            'bg-transparent font-semibold',
                            paused
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
