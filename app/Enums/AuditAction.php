<?php

namespace App\Enums;

enum AuditAction: string
{
    case Create = 'CREATE';
    case Update = 'UPDATE';
    case Delete = 'DELETE';
    case Restore = 'RESTORE';
    case Login = 'LOGIN';
    case Logout = 'LOGOUT';
    case Invite = 'INVITE';
    case Verify = 'VERIFY';
    case Revoke = 'REVOKE';
}
