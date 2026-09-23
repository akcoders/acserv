<?php

namespace App\Auth;

use App\Enums\OtpChannel;
use App\Models\User;
use App\Notifications\OtpCodeNotification;
use Illuminate\Support\Facades\Notification;

class NotificationOtpSender implements OtpSender
{
    public function send(User $user, OtpChannel $channel, string $code): void
    {
        $email = $user->email ?? config('mail.from.address');

        Notification::route('mail', $email)
            ->notify(new OtpCodeNotification($code, $channel));
    }
}
