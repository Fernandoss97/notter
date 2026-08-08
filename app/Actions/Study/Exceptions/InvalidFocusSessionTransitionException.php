<?php

namespace App\Actions\Study\Exceptions;

use RuntimeException;

class InvalidFocusSessionTransitionException extends RuntimeException
{
    public static function alreadyActive(): self
    {
        return new self('Já existe uma sessão de foco em andamento.');
    }

    public static function notRunning(): self
    {
        return new self('A sessão não está em andamento.');
    }

    public static function notPaused(): self
    {
        return new self('A sessão não está pausada.');
    }

    public static function alreadyCompleted(): self
    {
        return new self('A sessão já foi encerrada.');
    }

    public static function phaseNotFinished(): self
    {
        return new self('O ciclo atual ainda não terminou.');
    }
}
