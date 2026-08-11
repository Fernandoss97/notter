import type { CycleLapPassage } from '@/types/study';

/**
 * Os pares de disciplinas da mesma família que ficaram em sequência no
 * rodízio (§3 da spec): dois direitos seguidos, ou duas de exatas, cansam
 * a mesma parte da cabeça, e trocar de estímulo é a razão de existir do
 * ciclo.
 *
 * Lê a fila da volta, não a lista de cadeiras: com peso maior que 1 uma
 * disciplina passa mais de uma vez, e é entre as passagens — não entre as
 * cadeiras — que a vizinhança acontece. A fila já vem do servidor sem as
 * cadeiras fora do rodízio, que não separam ninguém porque não passam.
 *
 * "Outra" não conta. Ela é a ausência de resposta, não uma família — não
 * se sabe se duas matérias sem etiqueta são parecidas, e avisar sobre o
 * que não se sabe treina o usuário a ignorar avisos.
 *
 * Duas passagens da mesma disciplina em sequência também não contam: elas
 * são o peso fazendo o que foi pedido, e "Direito e Direito são da mesma
 * área" não é conselho, é a tela falando sozinha.
 *
 * O resultado é aviso, nunca impedimento. Há motivo legítimo para dois
 * direitos seguidos, e a lista existe para ser vista e ignorada quando for
 * o caso.
 */
export function findAreaClashes(lapOrder: CycleLapPassage[]): string[] {
    const clashes: string[] = [];

    for (let i = 1; i < lapOrder.length; i++) {
        const previous = lapOrder[i - 1];
        const current = lapOrder[i];

        if (
            current.slotId !== previous.slotId &&
            current.subjectArea === previous.subjectArea &&
            current.subjectArea !== 'other'
        ) {
            clashes.push(`${previous.subjectName} e ${current.subjectName}`);
        }
    }

    return clashes;
}
