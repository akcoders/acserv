<?php

namespace App\Enums;

enum PayoutStatus: string
{
    case Draft = 'DRAFT';
    case Processing = 'PROCESSING';
    case Processed = 'PROCESSED';
    case Paid = 'PAID';
    case Disputed = 'DISPUTED';
    case Cancelled = 'CANCELLED';
}
