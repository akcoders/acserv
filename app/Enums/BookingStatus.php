<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'PENDING';
    case Confirmed = 'CONFIRMED';
    case Converted = 'CONVERTED';
    case Cancelled = 'CANCELLED';
}
