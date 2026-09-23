<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case Push = 'PUSH';
    case Email = 'EMAIL';
    case WhatsApp = 'WHATSAPP';
    case Sms = 'SMS';
}
