import { ProgressBar } from '@/components/ui/progress-bar';
import { ReviewStatusBadge } from '@/components/ui/review-status-badge';
import { cn } from '@/lib/utils';
import type { ReviewStatus } from '@/types/study';

interface SubjectCardProps {
    subject: string;
    topicsDone: number;
    topicsTotal: number;
    progress: number;
    reviewStatus?: ReviewStatus;
    onClick?: () => void;
    className?: string;
}

export function SubjectCard({
    subject,
    topicsDone,
    topicsTotal,
    progress,
    reviewStatus,
    onClick,
    className,
}: SubjectCardProps) {
    const cardClassName = cn(
        'flex flex-col gap-3 rounded-md border border-border bg-card p-5 text-left shadow-sm',
        onClick && [
            'cursor-pointer outline-none',
            'transition-[border-color,box-shadow,scale] duration-fast ease-out',
            'hover:border-border-strong hover:shadow-md',
            'focus-visible:ring-[3px] focus-visible:ring-ring/50',
            'active:scale-[0.99] active:duration-instant',
        ],
        className,
    );

    const content = (
        <>
            <div className="flex items-start justify-between gap-2">
                <div>
                    <div className="text-md font-bold text-foreground">
                        {subject}
                    </div>
                    <div className="mt-0.5 text-xs text-muted-foreground">
                        {topicsDone}/{topicsTotal} tópicos concluídos
                    </div>
                </div>
                {reviewStatus && <ReviewStatusBadge status={reviewStatus} />}
            </div>
            <ProgressBar value={progress} showLabel />
        </>
    );

    // Um card clicável precisa ser um botão de verdade: `div` com `onClick`
    // não recebe foco, não responde a Enter/Espaço e não é anunciado.
    if (onClick) {
        return (
            <button type="button" onClick={onClick} className={cardClassName}>
                {content}
            </button>
        );
    }

    return <div className={cardClassName}>{content}</div>;
}
