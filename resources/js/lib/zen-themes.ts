import type { CSSProperties } from 'react';

/**
 * As cores do modo zen.
 *
 * Os ramps `--focus-*` do app mantêm luminosidade fixa e variam só o
 * matiz, então trocar a cor tema é trocar um número — o contraste que a
 * paleta original garante continua valendo em qualquer tema.
 *
 * O croma não pode ser fixo junto com a luminosidade: o gamut sRGB não é
 * uniforme e 0.18 só é alcançável em parte do círculo. Perto do verde e
 * do amarelo a cor seria recortada e voltaria lavada, com os degraus 400
 * e 500 indistinguíveis. Cada tema traz o fator que o mantém dentro do
 * gamut.
 *
 * Nenhum tema chega perto do matiz 175: é o verde-água de `--pausa-*`, e
 * um foco dessa cor apagaria a distinção entre estudar e descansar.
 */
export type ZenThemeId =
    'coral' | 'ambar' | 'verde' | 'azul' | 'indigo' | 'magenta';

export type ZenTheme = {
    id: ZenThemeId;
    label: string;
    /** Matiz oklch, em graus. */
    hue: number;
    /** Multiplicador do croma do ramp base — 1 é o coral do app. */
    chroma: number;
};

export const ZEN_THEMES: readonly ZenTheme[] = [
    { id: 'coral', label: 'Coral', hue: 35, chroma: 1 },
    { id: 'ambar', label: 'Âmbar', hue: 75, chroma: 0.85 },
    { id: 'verde', label: 'Verde', hue: 135, chroma: 0.85 },
    { id: 'azul', label: 'Azul', hue: 245, chroma: 0.9 },
    { id: 'indigo', label: 'Índigo', hue: 285, chroma: 0.95 },
    { id: 'magenta', label: 'Magenta', hue: 325, chroma: 0.95 },
];

export const DEFAULT_ZEN_THEME: ZenThemeId = 'coral';

export function findZenTheme(id: string | null | undefined): ZenTheme {
    return (
        ZEN_THEMES.find((theme) => theme.id === id) ??
        ZEN_THEMES.find((theme) => theme.id === DEFAULT_ZEN_THEME)!
    );
}

/**
 * As duas variáveis que o CSS de `[data-zen-theme]` consome. Ficam inline
 * no container em vez de virar seis blocos de regra — um tema é um par de
 * números, não uma folha de estilo.
 */
export function zenThemeVars(theme: ZenTheme): CSSProperties {
    return {
        '--zen-hue': String(theme.hue),
        '--zen-chroma': String(theme.chroma),
    } as CSSProperties;
}
