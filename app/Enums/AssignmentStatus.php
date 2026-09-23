<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case Assigned = 'ASSIGNED';
    case Accepted = 'ACCEPTED';
    case Declined = 'DECLINED';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
}
