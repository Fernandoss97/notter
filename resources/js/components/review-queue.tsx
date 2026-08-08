import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Panel } from '@/components/panel';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { complete as completeReview } from '@/routes/reviews';
import type { DueReview } from '@/types/study';

/**
 * A fila de revisões espaçadas devidas hoje (C1). Fica na tela de foco
 * porque é ali que a decisão "o que estudar agora" acontece: cada item
 * pode virar a próxima sessão (`onStudy`) ou ser dado como feito.
 *
 * `muted` é o modo silencioso durante o foco: a fila continua acessível,
 * mas para de disputar atenção com o cronômetro.
 */
export function ReviewQueue({
    reviews,
    muted = false,
    onStudy,
}: {
    reviews: DueReview[];
    muted?: boolean;
    onStudy?: (review: DueReview) => void;
}) {
    const [completing, setCompleting] = useState<number[]>([]);
    const pending = reviews.filter((review) => !completing.includes(review.id));
    const overdueCount = pending.filter((review) => review.overdue).length;

    function markDone(review: DueReview) {
        setCompleting((ids) => [...ids, review.id]);
        router.post(
            completeReview(review.id).url,
            {},
            {
                preserveScroll: true,
                showProgress: false,
                onError: () =>
                    setCompleting((ids) =>
                        ids.filter((id) => id !== review.id),
                    ),
            },
        );
    }

    return (
        <Panel
            title="Revisões de hoje"
            action={
                overdueCount > 0 &&
                !muted && (
                    <span className="rounded-full bg-status-danger-bg px-2 py-0.5 text-xs font-semibold text-status-danger">
                        {overdueCount} atrasada{overdueCount > 1 ? 's' : ''}
                    </span>
                )
            }
            className={cn(
                'transition-opacity duration-base ease-out',
                muted && 'opacity-60',
            )}
        >
            {pending.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Nenhuma revisão para hoje. Conclua tópicos no plano para
                    agendar as próximas.
                </p>
            ) : (
                <ul className="flex flex-col gap-1">
                    {pending.map((review) => (
                        <li
                            key={review.id}
                            className="flex items-center gap-3 rounded-sm px-2 py-1.5 transition-colors duration-fast ease-out hover:bg-muted"
                        >
                            <span
                                aria-hidden
                                className={cn(
                                    'size-1.5 shrink-0 rounded-full',
                                    review.overdue
                                        ? 'bg-status-danger'
                                        : 'bg-status-success',
                                )}
                            />
                            <span className="flex min-w-0 flex-1 flex-col">
                                <span className="truncate text-sm text-foreground">
                                    {review.topicName}
                                </span>
                                <span className="truncate text-xs text-muted-foreground">
                                    {review.subjectName} · {review.sequence}ª
                                    revisão · {review.dueOn}
                                </span>
                            </span>
                            {onStudy && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => onStudy(review)}
                                >
                                    Estudar
                                </Button>
                            )}
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={() => markDone(review)}
                            >
                                Feito
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}
