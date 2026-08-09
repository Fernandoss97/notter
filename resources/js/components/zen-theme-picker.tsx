import { cn } from '@/lib/utils';
import { ZEN_THEMES, zenThemeVars } from '@/lib/zen-themes';
import type { ZenThemeId } from '@/lib/zen-themes';

/**
 * A escolha da cor do modo zen. Fica dentro do overlay, discreta: quem
 * está em zen escolheu a cor uma vez e depois quer esquecer que ela é
 * escolhível.
 *
 * Cada amostra carrega o próprio `data-zen-theme`, então é pintada pelo
 * mesmo CSS que pinta o modo zen inteiro — inclusive no tema escuro, sem
 * uma segunda tabela de cores para manter em sincronia.
 */
export function ZenThemePicker({
    value,
    onValueChange,
    className,
}: {
    value: ZenThemeId;
    onValueChange: (id: ZenThemeId) => void;
    className?: string;
}) {
    return (
        <div
            role="group"
            aria-label="Cor do modo zen"
            className={cn('flex items-center gap-2', className)}
        >
            {ZEN_THEMES.map((theme) => (
                <button
                    key={theme.id}
                    type="button"
                    data-zen-theme={theme.id}
                    style={zenThemeVars(theme)}
                    aria-label={theme.label}
                    aria-pressed={theme.id === value}
                    onClick={() => onValueChange(theme.id)}
                    className={cn(
                        'size-5 rounded-full bg-focus-500 transition-[transform,box-shadow] duration-fast ease-out',
                        'hover:scale-110 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none',
                        theme.id === value &&
                            'ring-2 ring-focus-500 ring-offset-2 ring-offset-background',
                    )}
                />
            ))}
        </div>
    );
}
