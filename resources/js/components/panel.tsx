import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function Panel({
    title,
    action,
    pad = true,
    className,
    children,
}: {
    title?: string;
    action?: ReactNode;
    pad?: boolean;
    className?: string;
    children: ReactNode;
}) {
    return (
        <section
            className={cn(
                'flex flex-col rounded-md border border-border bg-card shadow-sm',
                className,
            )}
        >
            {(title || action) && (
                <header className="flex items-center justify-between gap-3 border-b border-border px-5 py-3.5">
                    {title && (
                        <span className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            {title}
                        </span>
                    )}
                    {action}
                </header>
            )}
            <div className={cn('flex flex-1 flex-col gap-4', pad && 'p-5')}>
                {children}
            </div>
        </section>
    );
}
