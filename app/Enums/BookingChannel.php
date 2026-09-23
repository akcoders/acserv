<?php

namespace App\Enums;

enum BookingChannel: string
{
    case Website = 'WEBSITE';
    case Phone = 'PHONE';
    case WhatsApp = 'WHATSAPP';
    case CustomerPwa = 'CUSTOMER_PWA';
    case WalkIn = 'WALKIN';
}
