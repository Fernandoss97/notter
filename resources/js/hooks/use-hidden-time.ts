import { usePage } from '@inertiajs/react';
import { createLocalPreference, usePreference } from '@/lib/local-preference';

/**
 * Esconder o tempo restante.
 *
 * O relógio regressivo convida a conferir o tempo a cada trinta segundos,
 * e conferir o tempo é sair do que se estava fazendo. Com ele escondido
 * sobra o progresso — barra e pontos de ciclo — que dá a noção grosseira
 * sem contagem alguma.
 *
 * A preferência é global de propósito: escondida só no cronômetro, a hora
 * voltaria pela barra de sessão e pelo título da aba, que é justamente
 * onde ela fica no canto do olho o tempo todo.
 *
 * O cookie espelho não é redundância: a barra de sessão é renderizada no
 * servidor, e sem ele o relógio apareceria por um instante a cada visita,
 * até a hidratação ler o `localStorage`.
 */
export const hiddenTimePreference = createLocalPreference<boolean>({
    key: 'hidden-time',
    cookie: 'hidden_time',
    fallback: false,
    parse: (stored) =>
        stored === 'true' ? true : stored === 'false' ? false : null,
    serialize: (hidden) => String(hidden),
});

export function useHiddenTime(): readonly [boolean, (hidden: boolean) => void] {
    // O que o servidor usou para montar o HTML. Divergir dele no primeiro
    // render seria um erro de hidratação.
    const { hiddenTime } = usePage().props;

    return usePreference(hiddenTimePreference, hiddenTime);
}
