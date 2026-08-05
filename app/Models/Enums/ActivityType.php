<?php

namespace App\Models\Enums;

enum ActivityType: string
{
    case Reading = 'reading';
    case Exercises = 'exercises';
    case Review = 'review';
    case Practice = 'practice';
}
