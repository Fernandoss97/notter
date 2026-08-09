<?php

namespace App\Concerns;

trait CalculatesPercentage
{
    /**
     * Percentual arredondado, ou `null` quando não há denominador —
     * disciplina sem questão resolvida não tem desempenho 0%, tem
     * desempenho nenhum, e as duas coisas se leem diferente na tela.
     */
    protected function percent(int $part, int $whole): ?int
    {
        return $whole > 0 ? (int) round($part / $whole * 100) : null;
    }
}
