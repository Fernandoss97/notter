import type { HTMLAttributes } from 'react';
import { SegmentedControl } from '@/components/ui/segmented-control';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';

const APPEARANCE_OPTIONS: { value: Appearance; label: string }[] = [
    { value: 'light', label: 'Claro' },
    { value: 'dark', label: 'Escuro' },
    { value: 'system', label: 'Sistema' },
];

export default function AppearanceToggleTab({
    className,
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    const { appearance, updateAppearance } = useAppearance();

    return (
        <div className={className} {...props}>
            <SegmentedControl
                options={APPEARANCE_OPTIONS}
                value={appearance}
                onValueChange={(value) => updateAppearance(value as Appearance)}
            />
        </div>
    );
}
