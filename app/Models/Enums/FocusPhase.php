<?php

namespace App\Models\Enums;

enum FocusPhase: string
{
    case Focus = 'focus';
    case ShortBreak = 'short_break';
    case LongBreak = 'long_break';

    public function isBreak(): bool
    {
        return $this !== self::Focus;
    }

    public function label(): string
    {
        return match ($this) {
            self::Focus => __('Foco'),
            self::ShortBreak => __('Pausa curta'),
            self::LongBreak => __('Pausa longa'),
        };
    }
}
