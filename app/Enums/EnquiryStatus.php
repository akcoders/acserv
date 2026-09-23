<?php

namespace App\Enums;

enum EnquiryStatus: string
{
    case New = 'NEW';
    case Contacted = 'CONTACTED';
    case Qualified = 'QUALIFIED';
    case Converted = 'CONVERTED';
    case Lost = 'LOST';
    case Spam = 'SPAM';
}
