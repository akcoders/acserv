<?php

namespace App\Enums;

enum OtpChannel: string
{
    case Email = 'EMAIL';
    case Sms = 'SMS';
}
