<?php

namespace App\Notifications;

use App\Enums\OtpChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $code,
        private readonly OtpChannel $requestedChannel,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('auth.otp_subject'))
            ->greeting(__('auth.otp_greeting'))
            ->line(__('auth.otp_intro'))
            ->line($this->code)
            ->line(__('auth.otp_expiry', [
                'minutes' => config('acserv.otp.ttl_minutes'),
                'attempts' => config('acserv.otp.max_attempts'),
            ]))
            ->line(__('auth.otp_ignore'));

        if ($this->requestedChannel === OtpChannel::Sms) {
            $message->line(__('auth.sms_stub_notice'));
        }

        return $message;
    }
}
