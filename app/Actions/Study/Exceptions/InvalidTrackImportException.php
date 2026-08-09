<?php

namespace App\Actions\Study\Exceptions;

use RuntimeException;

class InvalidTrackImportException extends RuntimeException
{
    public static function atLine(int $line, string $reason): self
    {
        return new self(__('Linha :line: :reason', ['line' => $line, 'reason' => $reason]));
    }

    public static function empty(): self
    {
        return new self(__('Não há nenhuma tarefa no texto colado.'));
    }

    public static function tooManyLines(int $limit): self
    {
        return new self(__('São no máximo :limit linhas por importação.', ['limit' => $limit]));
    }
}
