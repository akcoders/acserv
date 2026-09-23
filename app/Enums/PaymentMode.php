<?php

namespace App\Enums;

enum PaymentMode: string
{
    case Cash = 'CASH';
    case Upi = 'UPI';
    case Card = 'CARD';
    case NetBanking = 'NETBANKING';
    case Wallet = 'WALLET';
    case Credit = 'CREDIT';
}
