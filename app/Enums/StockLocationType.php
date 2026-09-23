<?php

namespace App\Enums;

enum StockLocationType: string
{
    case Warehouse = 'WAREHOUSE';
    case Van = 'VAN';
    case TechnicianKit = 'TECHNICIAN_KIT';
}
