import { useEffect } from 'react';

type WakeLockLike = {
    released: boolean;
    release: () => Promise<void>;
};

type WakeLockCapableNavigator = {
    wakeLock?: {
        request: (type: 'screen') => Promise<WakeLockLike>;
    };
};

/**
 * Mantém a tela acesa enquanto o pomodoro corre.
 *
 * Um cronômetro que a pessoa olha de canto de olho não gera atividade
 * nenhuma para o sistema operacional: sem isso a tela apaga no meio do
 * ciclo, justamente quando ela está lendo em papel ou resolvendo questão.
 *
 * O navegador solta a trava sozinho quando a aba sai de vista — daí o
 * novo pedido no `visibilitychange`, sem o qual o bloqueio nunca volta.
 * Tudo é oportunista: navegador sem a API, permissão negada ou bateria
 * fraca simplesmente não travam nada.
 */
export function useWakeLock(active: boolean): void {
    useEffect(() => {
        const wakeLock = (navigator as Navigator & WakeLockCapableNavigator)
            .wakeLock;

        if (!active || !wakeLock) {
            return;
        }

        let sentinel: WakeLockLike | null = null;
        let cancelled = false;

        const request = async () => {
            if (cancelled || (sentinel && !sentinel.released)) {
                return;
            }

            try {
                const next = await wakeLock.request('screen');

                if (cancelled) {
                    void next.release().catch(() => {});

                    return;
                }

                sentinel = next;
            } catch {
                // Negado, sem bateria ou aba oculta na hora do pedido.
            }
        };

        const onVisibilityChange = () => {
            if (document.visibilityState === 'visible') {
                void request();
            }
        };

        void request();
        document.addEventListener('visibilitychange', onVisibilityChange);

        return () => {
            cancelled = true;
            document.removeEventListener(
                'visibilitychange',
                onVisibilityChange,
            );

            if (sentinel && !sentinel.released) {
                void sentinel.release().catch(() => {});
            }
        };
    }, [active]);
}
