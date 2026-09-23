<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Jobs\DeliverNotification;
use App\Models\NotificationLog;
use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Support\Str;

class NotificationDispatcher
{
    /** @param array<string, mixed> $data */
    public function queue(string $event, User $recipient, array $data = []): void
    {
        foreach (NotificationChannel::cases() as $channel) {
            if (! $this->allows($recipient, $event, $channel)) {
                continue;
            }

            $template = NotificationTemplate::query()
                ->where('event', $event)
                ->where('channel', $channel)
                ->where('is_active', true)
                ->whereIn('locale', [app()->getLocale(), 'en_IN'])
                ->orderByRaw('locale = ? desc', [app()->getLocale()])
                ->first();

            if ($template === null) {
                continue;
            }

            $destination = $this->destination($recipient, $channel);

            if ($destination === null) {
                continue;
            }

            $log = NotificationLog::query()->create([
                'notification_template_id' => $template->getKey(),
                'user_id' => $recipient->getKey(),
                'event' => $event,
                'channel' => $channel,
                'recipient_masked' => $this->mask($destination),
                'status' => NotificationStatus::Queued,
                'metadata' => ['data' => $data],
            ]);

            DeliverNotification::dispatch($log->getKey())->afterCommit();
        }
    }

    private function allows(User $user, string $event, NotificationChannel $channel): bool
    {
        $preference = NotificationPreference::query()
            ->where('user_id', $user->getKey())
            ->whereIn('event', [$event, '*'])
            ->where('channel', $channel)
            ->orderByRaw('event = ? desc', [$event])
            ->first();

        if ($preference === null) {
            return true;
        }

        if (! $preference->is_enabled) {
            return false;
        }

        if ($preference->quiet_starts_at === null || $preference->quiet_ends_at === null) {
            return true;
        }

        $currentTime = now($preference->timezone)->format('H:i:s');
        $starts = (string) $preference->quiet_starts_at;
        $ends = (string) $preference->quiet_ends_at;
        $isQuiet = $starts < $ends
            ? $currentTime >= $starts && $currentTime < $ends
            : $currentTime >= $starts || $currentTime < $ends;

        return ! $isQuiet;
    }

    private function destination(User $user, NotificationChannel $channel): ?string
    {
        return match ($channel) {
            NotificationChannel::Email => $user->email,
            NotificationChannel::Sms, NotificationChannel::WhatsApp => $user->phone,
            NotificationChannel::Push => $this->hasOneSignalCredentials()
                ? (string) $user->getKey()
                : $user->pushSubscriptions()
                    ->whereNull('revoked_at')
                    ->latest('last_used_at')
                    ->first()?->endpoint,
        };
    }

    private function hasOneSignalCredentials(): bool
    {
        return filled(config('services.onesignal.app_id'))
            && filled(config('services.onesignal.api_key'));
    }

    private function mask(string $destination): string
    {
        if (str_contains($destination, '@')) {
            [$name, $domain] = explode('@', $destination, 2);

            return Str::substr($name, 0, 2).'***@'.$domain;
        }

        return '***'.Str::substr($destination, -4);
    }
}
