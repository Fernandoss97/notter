import type { ReactNode } from 'react';

export function Page({ children }: { children: ReactNode }) {
    return (
        <div className="mx-auto flex max-w-app flex-col gap-6 p-10">
            {children}
        </div>
    );
}
