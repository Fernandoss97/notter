import type { ReactNode } from 'react';

export function PageHeader({
    title,
    subtitle,
    actions,
}: {
    title: string;
    subtitle?: string;
    actions?: ReactNode;
}) {
    return (
        <div className="flex items-end justify-between gap-6">
            <div>
                <h1 className="text-2xl font-extrabold tracking-tight text-foreground">
                    {title}
                </h1>
                {subtitle && (
                    <p className="mt-1 text-base text-fg-2">{subtitle}</p>
                )}
            </div>
            {actions && <div className="flex shrink-0 gap-3">{actions}</div>}
        </div>
    );
}
