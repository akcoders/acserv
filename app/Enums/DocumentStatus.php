<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Draft = 'DRAFT';
    case Sent = 'SENT';
    case Accepted = 'ACCEPTED';
    case Rejected = 'REJECTED';
    case Approved = 'APPROVED';
    case PartiallyPaid = 'PARTIALLY_PAID';
    case Paid = 'PAID';
    case Overdue = 'OVERDUE';
    case Cancelled = 'CANCELLED';
}
