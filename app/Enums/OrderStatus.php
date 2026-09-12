<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'PENDING';
    case AVAILABLE = 'AVAILABLE';
    case RECOMMENDED = 'RECOMMENDED';
    case ACCEPTED = 'ACCEPTED';
    case PICKING_UP = 'PICKING_UP';
    case PICKED_UP = 'PICKED_UP';
    case DELIVERING = 'DELIVERING';
    case DELIVERED = 'DELIVERED';
    case EXPIRED = 'EXPIRED';
    case REJECTED = 'REJECTED';
}
