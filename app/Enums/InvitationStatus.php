<?php

namespace App\Enums;

enum InvitationStatus: string
{
    case Pending = 'PENDING';
    case Accepted = 'ACCEPTED';
    case Revoked = 'REVOKED';
    case Expired = 'EXPIRED';
}
