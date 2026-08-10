<?php

namespace App\Models\Enums;

enum SubjectMode: string
{
    case Reading = 'reading';
    case Review = 'review';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Reading => __('Leitura'),
            self::Review => __('Revisão'),
            self::Done => __('Encerrada'),
        };
    }

    /**
     * O modo consome uma vaga do núcleo de matérias em primeiro contato.
     * É o que a spec (§4) pede para monitorar: teoria nova é o que mais
     * cansa, e passar de seis ao mesmo tempo é o começo do ciclo inchado.
     */
    public function isFirstContact(): bool
    {
        return $this === self::Reading;
    }
}
