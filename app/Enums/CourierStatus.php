<?php

namespace App\Enums;

enum CourierStatus: string
{
    case AVAILABLE = 'AVAILABLE';
    case GOING_TO_PICKUP = 'GOING_TO_PICKUP';
    case WAITING_AT_RESTAURANT = 'WAITING_AT_RESTAURANT';
    case DELIVERING = 'DELIVERING';
}
