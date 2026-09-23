<?php

namespace App\Enums;

enum WarrantyType: string
{
    case Manufacturer = 'MANUFACTURER';
    case Extended = 'EXTENDED';
    case Amc = 'AMC';
    case None = 'NONE';
}
