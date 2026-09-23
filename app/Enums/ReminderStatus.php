<?php

namespace App\Enums;

enum ReminderStatus: string
{
    case Pending = 'PENDING';
    case Queued = 'QUEUED';
    case Sent = 'SENT';
    case Skipped = 'SKIPPED';
    case Failed = 'FAILED';
}
