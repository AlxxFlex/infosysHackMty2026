<?php

namespace App\Enums;

enum RecommendationStatus: string
{
    case PENDING = 'PENDING';
    case ACCEPTED = 'ACCEPTED';
    case SUPERSEDED = 'SUPERSEDED';
    case REJECTED = 'REJECTED';
}
