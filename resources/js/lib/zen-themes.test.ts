import { describe, expect, it } from 'vitest';
import {
    DEFAULT_ZEN_THEME,
    findZenTheme,
    ZEN_THEMES,
    zenThemeVars,
} from './zen-themes';

describe('zen themes', () => {
    it('falls back to the default for anything it does not know', () => {
        expect(findZenTheme('roxo-neon').id).toBe(DEFAULT_ZEN_THEME);
        expect(findZenTheme(null).id).toBe(DEFAULT_ZEN_THEME);
        expect(findZenTheme(undefined).id).toBe(DEFAULT_ZEN_THEME);
    });

    it('finds a theme it does know', () => {
        expect(findZenTheme('azul').label).toBe('Azul');
    });

    it('hands the CSS exactly the two numbers it reads', () => {
        expect(zenThemeVars(findZenTheme('coral'))).toEqual({
            '--zen-hue': '35',
            '--zen-chroma': '1',
        });
    });

    it('keeps every theme away from the break colour', () => {
        // `--pausa-*` vive no matiz 175. Um tema de foco perto dali
        // apagaria a diferença entre estudar e descansar.
        for (const theme of ZEN_THEMES) {
            expect(Math.abs(theme.hue - 175)).toBeGreaterThan(30);
        }
    });

    it('keeps the chroma within what sRGB can actually show', () => {
        for (const theme of ZEN_THEMES) {
            expect(theme.chroma).toBeGreaterThan(0);
            expect(theme.chroma).toBeLessThanOrEqual(1);
        }
    });
});
