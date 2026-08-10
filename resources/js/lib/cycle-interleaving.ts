import type { CycleSlotSummary } from '@/types/study';

/**
 * Os pares de disciplinas da mesma família que ficaram em sequência no
 * rodízio (§3 da spec): dois direitos seguidos, ou duas de exatas, cansam
 * a mesma parte da cabeça, e trocar de estímulo é a razão de existir do
 * ciclo.
 *
 * "Outra" não conta. Ela é a ausência de resposta, não uma família — não
 * se sabe se duas matérias sem etiqueta são parecidas, e avisar sobre o
 * que não se sabe treina o usuário a ignorar avisos.
 *
 * Cadeiras fora do rodízio são puladas, e não interrompem a sequência:
 * quem não participa da volta não separa ninguém.
 *
 * O resultado é aviso, nunca impedimento. Há motivo legítimo para dois
 * direitos seguidos, e a lista existe para ser vista e ignorada quando for
 * o caso.
 */
export function findAreaClashes(slots: CycleSlotSummary[]): string[] {
    const active = slots.filter((slot) => slot.state === 'active');
    const clashes: string[] = [];

    for (let i = 1; i < active.length; i++) {
        const previous = active[i - 1];
        const current = active[i];

        if (
            current.subjectArea === previous.subjectArea &&
            current.subjectArea !== 'other'
        ) {
            clashes.push(`${previous.subjectName} e ${current.subjectName}`);
        }
    }

    return clashes;
}
