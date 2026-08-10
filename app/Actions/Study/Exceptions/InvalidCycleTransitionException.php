<?php

namespace App\Actions\Study\Exceptions;

use RuntimeException;

class InvalidCycleTransitionException extends RuntimeException
{
    public static function blockAlreadyCompleted(): self
    {
        return new self('Este bloco do ciclo já foi encerrado.');
    }

    public static function subjectAlreadyInCycle(): self
    {
        return new self('Esta disciplina já está no ciclo.');
    }

    public static function subjectOutsidePlan(): self
    {
        return new self('A disciplina não pertence ao plano ativo.');
    }
}
