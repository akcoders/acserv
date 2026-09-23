<?php

namespace App\Enums;

enum OtpPurpose: string
{
    case TenantRegistration = 'TENANT_REGISTRATION';
    case Login = 'LOGIN';
    case Invitation = 'INVITATION';
    case PasskeyRegistration = 'PASSKEY_REGISTRATION';
}
