import type { ReactNode } from 'react';

const MAX_WIDTH = {
    app: 'max-w-app',
    narrow: 'max-w-4xl',
} as const;

export function Page({
    children,
    maxWidth = 'app',
}: {
    children: ReactNode;
    maxWidth?: keyof typeof MAX_WIDTH;
}) {
    return (
        <div
            className={`mx-auto flex w-full flex-col gap-6 p-10 ${MAX_WIDTH[maxWidth]}`}
        >
            {children}
        </div>
    );
}
