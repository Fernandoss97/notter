import { useEffect, useRef } from 'react';

/**
 * Amarra a tela cheia do navegador ao modo zen.
 *
 * Sem isso o zen esconde a interface do app e deixa a do navegador —
 * abas, barra de endereço — que é justamente a parte que puxa a atenção
 * para fora do estudo.
 *
 * O caminho de volta importa tanto quanto o de ida: em tela cheia o Esc é
 * consumido pelo navegador e nunca chega ao atalho do zen. Por isso a
 * saída da tela cheia avisa quem chamou, para o zen sair junto — do
 * contrário um Esc deixaria a pessoa presa no zen sem tela cheia.
 */
export function useFullscreen(active: boolean, onExit?: () => void): void {
    const onExitRef = useRef(onExit);

    useEffect(() => {
        onExitRef.current = onExit;
    });

    useEffect(() => {
        if (!active || typeof document === 'undefined') {
            return;
        }

        const element = document.documentElement;

        // Navegador sem a API, iframe sem permissão ou gesto expirado: o
        // zen continua valendo, só não ocupa a tela inteira.
        void element.requestFullscreen?.().catch(() => {});

        const onChange = () => {
            if (!document.fullscreenElement) {
                onExitRef.current?.();
            }
        };

        document.addEventListener('fullscreenchange', onChange);

        return () => {
            document.removeEventListener('fullscreenchange', onChange);

            if (document.fullscreenElement) {
                void document.exitFullscreen?.().catch(() => {});
            }
        };
    }, [active]);
}
