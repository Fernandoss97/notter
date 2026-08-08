import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

/**
 * Continuidade entre rotas: a troca de página do Inertia acontece num frame só,
 * o que lê como corte. A chave por componente força a remontagem do invólucro,
 * e o `animate-in` roda uma entrada curta (opacidade + 4px) sobre o conteúdo
 * novo. Só a entrada é animada — animar a saída obrigaria a segurar a página
 * antiga na tela e adiaria justamente o que o usuário pediu.
 */
export function PageTransition({ children }: { children: ReactNode }) {
    const { component } = usePage();

    return (
        <div
            key={component}
            className="flex flex-1 animate-in flex-col duration-base ease-out fade-in-0 slide-in-from-bottom-1"
        >
            {children}
        </div>
    );
}
