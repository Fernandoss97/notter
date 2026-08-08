import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { PageTransition } from '@/components/page-transition';
import { SessionBar } from '@/components/session-bar';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({ children }: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                {/* Fora do PageTransition de propósito: a barra de sessão é
                    persistente e não deve re-entrar a cada navegação. */}
                <SessionBar />
                <PageTransition>{children}</PageTransition>
            </AppContent>
        </AppShell>
    );
}
