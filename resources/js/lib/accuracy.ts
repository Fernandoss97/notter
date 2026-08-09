/**
 * A cor de um percentual de acerto. Os cortes são os mesmos em toda a
 * aplicação: quem lê 68% no histórico e 68% na evolução por disciplina tem
 * que ver a mesma cor, senão a cor deixa de significar algo.
 *
 * `null` (nada resolvido) não é zero: ausência não é desempenho ruim.
 */
export function accuracyColor(accuracy: number | null): string {
    if (accuracy === null) {
        return 'text-muted-foreground';
    }

    if (accuracy >= 70) {
        return 'text-status-success';
    }

    return accuracy >= 50 ? 'text-status-warning' : 'text-status-danger';
}
