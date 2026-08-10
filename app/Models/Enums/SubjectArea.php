<?php

namespace App\Models\Enums;

enum SubjectArea: string
{
    case Law = 'law';
    case Exact = 'exact';
    case Languages = 'languages';
    case Humanities = 'humanities';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Law => __('Direito'),
            self::Exact => __('Exatas'),
            self::Languages => __('Linguagens'),
            self::Humanities => __('Humanas'),
            self::Other => __('Outra'),
        };
    }

    /**
     * Duas disciplinas cansam do mesmo jeito quando são da mesma família —
     * exceto "Outra", que é a ausência de resposta e não uma família. Pôr
     * duas "Outra" em sequência não é motivo de aviso, porque não se sabe
     * se elas são parecidas.
     */
    public function clashesWith(self $other): bool
    {
        return $this === $other && $this !== self::Other;
    }
}
