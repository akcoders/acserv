<?php

namespace App\Auth;

use App\Enums\OtpChannel;
use App\Models\User;

interface OtpSender
{
    public function send(User $user, OtpChannel $channel, string $code): void;
}
