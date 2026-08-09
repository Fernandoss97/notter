import { Link } from '@inertiajs/react';
import { Palette, ShieldCheck, Timer, User } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editPomodoro } from '@/routes/pomodoro-settings';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const settingsNavItems: NavItem[] = [
    {
        title: 'Perfil',
        href: editProfile(),
        icon: User,
    },
    {
        title: 'Pomodoro',
        href: editPomodoro(),
        icon: Timer,
    },
    {
        title: 'Segurança',
        href: editSecurity(),
        icon: ShieldCheck,
    },
    {
        title: 'Aparência',
        href: editAppearance(),
        icon: Palette,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <Page>
            <PageHeader
                title="Configurações"
                subtitle="Conta, segurança e preferências de estudo"
            />

            <div className="flex flex-col gap-8 lg:flex-row lg:gap-10">
                <aside className="lg:w-52 lg:shrink-0">
                    <nav
                        className="flex flex-col gap-1"
                        aria-label="Configurações"
                    >
                        {settingsNavItems.map((item) => {
                            const isActive = isCurrentOrParentUrl(item.href);

                            return (
                                <Link
                                    key={toUrl(item.href)}
                                    href={item.href}
                                    prefetch
                                    aria-current={isActive ? 'page' : undefined}
                                    className={cn(
                                        'flex items-center gap-2.5 rounded-md px-3 py-2 text-sm transition-colors',
                                        isActive
                                            ? 'bg-muted font-medium text-foreground'
                                            : 'text-fg-2 hover:bg-muted/60 hover:text-foreground',
                                    )}
                                >
                                    {item.icon && (
                                        <item.icon className="size-4 shrink-0" />
                                    )}
                                    {item.title}
                                </Link>
                            );
                        })}
                    </nav>
                </aside>

                <section className="flex max-w-2xl min-w-0 flex-1 flex-col gap-6">
                    {children}
                </section>
            </div>
        </Page>
    );
}
