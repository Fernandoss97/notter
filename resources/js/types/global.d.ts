import type { Auth } from '@/types/auth';
import type { ActiveFocusSession } from '@/types/session';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            /** Esconder o tempo restante — do cookie, para o primeiro paint. */
            hiddenTime: boolean;
            activeSession: ActiveFocusSession | null;
            [key: string]: unknown;
        };
    }
}
