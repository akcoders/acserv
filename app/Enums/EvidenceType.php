<?php

namespace App\Enums;

enum EvidenceType: string
{
    case Start = 'START';
    case Before = 'BEFORE';
    case During = 'DURING';
    case After = 'AFTER';
    case Signature = 'SIGNATURE';
    case Invoice = 'INVOICE';
    case Other = 'OTHER';
}
