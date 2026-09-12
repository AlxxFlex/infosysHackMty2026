<?php

namespace App\Enums;

enum ShiftStatus: string
{
    case IDLE = 'IDLE';
    case RUNNING = 'RUNNING';
    case PAUSED = 'PAUSED';
    case FINISHED = 'FINISHED';
}
