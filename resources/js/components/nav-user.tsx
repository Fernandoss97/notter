import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, Settings } from 'lucide-react';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes';
import { edit as editPomodoro } from '@/routes/pomodoro-settings';
import { edit as editProfile } from '@/routes/profile';

export function NavUser() {
    const { auth } = usePage().props;
    const { isCurrentUrl } = useCurrentUrl();
    const cleanup = useMobileNavigation();

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    if (!auth.user) {
        return null;
    }

    // Qualquer tela de ajustes acende o item: o menu de configurações inteiro
    // mora atrás dele.
    const isOnSettings =
        isCurrentUrl(editProfile(), undefined, true) ||
        isCurrentUrl('/settings', undefined, true) ||
        isCurrentUrl(editPomodoro());

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton
                    asChild
                    isActive={isOnSettings}
                    tooltip="Configurações"
                    data-test="sidebar-menu-button"
                >
                    {/* O rótulo é o destino, não quem está logado: o item
                        leva às configurações, e o nome do usuário já está
                        na primeira tela de lá. */}
                    <Link href={editProfile()} prefetch onClick={cleanup}>
                        <Settings />
                        <span>Configurações</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>

            <SidebarMenuItem>
                <SidebarMenuButton
                    asChild
                    tooltip="Sair"
                    className="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100"
                >
                    <Link
                        href={logout()}
                        as="button"
                        onClick={handleLogout}
                        data-test="logout-button"
                    >
                        <LogOut />
                        <span>Sair</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
