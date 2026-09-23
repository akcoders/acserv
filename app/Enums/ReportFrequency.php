<?php

namespace App\Enums;

enum ReportFrequency: string
{
    case Daily = 'DAILY';
    case Weekly = 'WEEKLY';
    case Monthly = 'MONTHLY';
    case Quarterly = 'QUARTERLY';
}
