import { cn } from '@/lib/utils';

export function Dot({ className }: { className?: string }) {
    return (
        <span
            className={cn(
                'inline-block size-2 shrink-0 rounded-full bg-current',
                className,
            )}
        />
    );
}
