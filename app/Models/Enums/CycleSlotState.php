<?php

namespace App\Models\Enums;

enum CycleSlotState: string
{
    case Active = 'active';
    case Paused = 'paused';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('No rodízio'),
            self::Paused => __('Fora do rodízio'),
        };
    }
}
