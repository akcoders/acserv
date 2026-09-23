<?php

namespace App\Jobs;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\Role;
use App\Models\NotificationLog;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;

class DeliverNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public readonly string $notificationLogId)
    {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(TenantContext $tenantContext): void
    {
        $log = NotificationLog::withoutGlobalScopes()->findOrFail($this->notificationLogId);

        $tenantContext->set($log->tenant_id);

        try {
            $log->load(['template', 'user.pushSubscriptions' => fn ($query) => $query->whereNull('revoked_at')->latest('last_used_at')]);
            $template = $log->template;
            $user = $log->user;

            if ($template === null || $user === null) {
                throw new RuntimeException('Notification template or recipient is unavailable.');
            }

            $data = $log->metadata['data'] ?? [];
            $subject = $this->render((string) $template->subject, $data);
            $body = $this->render($template->body, $data);

            $providerId = match ($log->channel) {
                NotificationChannel::Email => $this->sendEmail((string) $user->email, $subject, $body),
                NotificationChannel::Sms => $this->sendHttp('sms', (string) $user->phone, $body),
                NotificationChannel::WhatsApp => $this->sendHttp('whatsapp', (string) $user->phone, $body),
                NotificationChannel::Push => $this->sendPush($user, $subject, $body),
            };

            $log->update([
                'status' => NotificationStatus::Sent,
                'provider' => $log->channel === NotificationChannel::Push && $this->hasOneSignalCredentials()
                    ? 'onesignal'
                    : $log->channel->value,
                'provider_id' => $providerId,
                'attempts' => $log->attempts + 1,
                'sent_at' => now(),
                'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            $log->update([
                'status' => NotificationStatus::Failed,
                'attempts' => $log->attempts + 1,
                'failed_at' => now(),
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
            ]);

            throw $exception;
        } finally {
            $tenantContext->clear();
        }
    }

    private function sendEmail(string $destination, string $subject, string $body): string
    {
        if ($destination === '') {
            throw new RuntimeException('Email destination is missing.');
        }

        Mail::raw($body, fn ($message) => $message->to($destination)->subject($subject));

        return 'mail-'.now()->format('YmdHis');
    }

    private function sendHttp(string $provider, string $destination, string $body): string
    {
        $url = config("services.{$provider}.url");
        $token = config("services.{$provider}.token");

        if (! is_string($url) || $url === '' || ! is_string($token) || $token === '') {
            throw new RuntimeException(ucfirst($provider).' provider is not configured.');
        }

        $response = Http::withToken($token)
            ->withHeaders(['Idempotency-Key' => $this->notificationLogId])
            ->connectTimeout(3)
            ->timeout(10)
            ->post($url, ['to' => $destination, 'message' => $body])
            ->throw();

        return (string) ($response->json('id') ?? $response->header('X-Request-Id') ?? now()->timestamp);
    }

    private function sendPush(User $user, string $subject, string $body): string
    {
        if ($this->hasOneSignalCredentials()) {
            return $this->sendOneSignalPush($user, $subject, $body);
        }

        return $this->sendLegacyPush($user->pushSubscriptions->first(), $subject, $body);
    }

    private function sendOneSignalPush(User $user, string $subject, string $body): string
    {
        $appId = (string) config('services.onesignal.app_id');
        $apiKey = (string) config('services.onesignal.api_key');
        $destination = match ($user->role) {
            Role::Technician => route('technician.dashboard'),
            Role::Customer => route('customer.dashboard'),
            default => route('admin.dashboard'),
        };

        $response = Http::withHeaders(['Authorization' => 'Key '.$apiKey])
            ->connectTimeout(3)
            ->timeout(10)
            ->post('https://api.onesignal.com/notifications', [
                'app_id' => $appId,
                'target_channel' => 'push',
                'include_aliases' => ['external_id' => [(string) $user->getKey()]],
                'headings' => ['en' => $subject !== '' ? $subject : 'ACServ update'],
                'contents' => ['en' => $body],
                'url' => $destination,
                'idempotency_key' => Uuid::uuid5(Uuid::NAMESPACE_URL, 'acserv-notification-'.$this->notificationLogId)->toString(),
            ])
            ->throw();

        $notificationId = $response->json('id');

        if (! is_string($notificationId) || $notificationId === '') {
            throw new RuntimeException('OneSignal accepted the request without creating a notification. Check that the recipient subscribed to push.');
        }

        return $notificationId;
    }

    private function sendLegacyPush(?PushSubscription $subscription, string $subject, string $body): string
    {
        $url = config('services.push.url');
        $token = config('services.push.token');

        if ($subscription === null || ! is_string($url) || $url === '' || ! is_string($token) || $token === '') {
            throw new RuntimeException('Push provider is not configured.');
        }

        $response = Http::withToken($token)
            ->withHeaders(['Idempotency-Key' => $this->notificationLogId])
            ->connectTimeout(3)
            ->timeout(10)
            ->post($url, [
                'endpoint' => $subscription->endpoint,
                'keys' => ['p256dh' => $subscription->public_key, 'auth' => $subscription->auth_token],
                'title' => $subject,
                'body' => $body,
            ])
            ->throw();

        return (string) ($response->json('id') ?? now()->timestamp);
    }

    private function hasOneSignalCredentials(): bool
    {
        return filled(config('services.onesignal.app_id'))
            && filled(config('services.onesignal.api_key'));
    }

    /** @param array<string, mixed> $data */
    private function render(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $template = str_replace('{{'.$key.'}}', (string) $value, $template);
            }
        }

        return $template;
    }
}
