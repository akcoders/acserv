<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case Pending = 'PENDING';
    case Queued = 'QUEUED';
    case Sent = 'SENT';
    case Delivered = 'DELIVERED';
    case Failed = 'FAILED';
    case Suppressed = 'SUPPRESSED';
}
