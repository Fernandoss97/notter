import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import { Panel } from '@/components/panel';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    return (
        <>
            <Head title="Aparência" />

            <Panel title="Tema">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <div className="text-sm font-medium text-foreground">
                            Modo de exibição
                        </div>
                        <div className="mt-0.5 text-xs text-muted-foreground">
                            Acompanha o sistema por padrão
                        </div>
                    </div>
                    <AppearanceTabs />
                </div>
            </Panel>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Aparência',
            href: editAppearance(),
        },
    ],
};
