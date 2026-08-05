<?php

namespace App\Models\Enums;

enum FocusSessionState: string
{
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';
}
