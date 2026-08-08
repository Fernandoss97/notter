import { useEffect } from 'react';

/**
 * Reflete o estado da sessão no título da aba. Numa tela onde a pessoa
 * fica horas, a aba costuma estar em segundo plano — o título é o único
 * lugar onde o cronômetro continua visível.
 *
 * `null` devolve o título que o `<Head>` da página tinha posto.
 */
export function useDocumentTitle(title: string | null) {
    useEffect(() => {
        if (title === null) {
            return;
        }

        const previous = document.title;
        document.title = title;

        return () => {
            document.title = previous;
        };
    }, [title]);
}
