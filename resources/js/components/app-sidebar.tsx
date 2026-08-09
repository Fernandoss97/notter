import { Link } from '@inertiajs/react';
import { BookOpen, History, LayoutGrid, ListChecks, Timer } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { show as showFocus } from '@/routes/focus';
import { index as showHistory } from '@/routes/history';
import { index as showPlan } from '@/routes/plans';
import { index as showTrack } from '@/routes/track';
import type { NavItem } from '@/types';

// Foco primeiro: é a tela onde o usuário passa a maior parte do tempo e
// para onde o login leva. O dashboard vira o lugar de olhar para trás.
const mainNavItems: NavItem[] = [
    {
        title: 'Foco',
        href: showFocus(),
        icon: Timer,
    },
    {
        title: 'Progresso',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Trilha',
        href: showTrack(),
        icon: ListChecks,
    },
    {
        title: 'Plano de estudos',
        href: showPlan(),
        icon: BookOpen,
    },
    {
        title: 'Histórico',
        href: showHistory(),
        icon: History,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()}>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
