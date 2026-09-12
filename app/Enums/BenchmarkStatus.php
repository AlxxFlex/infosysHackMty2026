<?php

namespace App\Enums;

enum BenchmarkStatus: string
{
    case PENDING = 'PENDING';
    case RUNNING = 'RUNNING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
}
