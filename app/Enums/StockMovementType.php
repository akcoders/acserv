<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Opening = 'OPENING';
    case Purchase = 'PURCHASE';
    case Transfer = 'TRANSFER';
    case Consumption = 'CONSUMPTION';
    case Return = 'RETURN';
    case Adjustment = 'ADJUSTMENT';
}
