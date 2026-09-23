<?php

namespace App\Enums;

enum AssetStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
    case Retired = 'RETIRED';
}
