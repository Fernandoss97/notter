import { createLocalPreference, usePreference } from '@/lib/local-preference';
import { DEFAULT_ZEN_THEME, findZenTheme, ZEN_THEMES } from '@/lib/zen-themes';
import type { ZenTheme, ZenThemeId } from '@/lib/zen-themes';

/**
 * A cor do modo zen é preferência de aparência por dispositivo, como o
 * tema claro/escuro de `use-appearance`.
 */
export const zenThemePreference = createLocalPreference<ZenThemeId>({
    key: 'zen-theme',
    fallback: DEFAULT_ZEN_THEME,
    parse: (stored) =>
        ZEN_THEMES.find((theme) => theme.id === stored)?.id ?? null,
    serialize: (id) => id,
});

export type UseZenThemeReturn = {
    readonly zenTheme: ZenTheme;
    readonly setZenTheme: (id: ZenThemeId) => void;
};

export function useZenTheme(): UseZenThemeReturn {
    const [id, setId] = usePreference(zenThemePreference);

    return { zenTheme: findZenTheme(id), setZenTheme: setId } as const;
}
