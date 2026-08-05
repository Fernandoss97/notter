import { cva } from 'class-variance-authority';
import type { VariantProps } from 'class-variance-authority';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

const statTileValueVariants = cva(
    'font-mono text-num-md leading-none tabular-nums',
    {
        variants: {
            color: {
                default: 'text-foreground',
                focus: 'text-focus-500',
                success: 'text-status-success',
                info: 'text-status-info',
                warning: 'text-status-warning',
                danger: 'text-status-danger',
            },
        },
        defaultVariants: {
            color: 'default',
        },
    },
);

interface StatTileProps extends VariantProps<typeof statTileValueVariants> {
    label: string;
    value: ReactNode;
    unit?: string;
    hint?: string;
    children?: ReactNode;
    className?: string;
}

export function StatTile({
    label,
    value,
    unit,
    hint,
    color,
    children,
    className,
}: StatTileProps) {
    return (
        <div
            className={cn(
                'flex flex-col gap-1.5 rounded-md border border-border bg-card p-5 shadow-sm',
                className,
            )}
        >
            <span className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {label}
            </span>
            <span className={statTileValueVariants({ color })}>
                {value}
                {unit && (
                    <span className="text-md text-muted-foreground">
                        {unit}
                    </span>
                )}
            </span>
            {children}
            {hint && (
                <span className="text-xs text-muted-foreground">{hint}</span>
            )}
        </div>
    );
}
