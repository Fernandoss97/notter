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
    return (
        <div
            onClick={onClick}
            className={cn(
                'flex flex-col gap-3 rounded-md border border-border bg-card p-5 shadow-sm',
                onClick && 'cursor-pointer',
                className,
            )}
        >
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
        </div>
    );
}
