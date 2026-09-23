<?php

namespace Tests\Feature;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Jobs\DeliverNotification;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Tests\TestCase;

class OneSignalPushTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();

        parent::tearDown();
    }

    public function test_configured_onesignal_sends_push_to_user_without_legacy_subscription(): void
    {
        config()->set('services.onesignal', ['app_id' => 'test-app-id', 'api_key' => 'server-secret']);
        [$tenant, $technician] = $this->technicianWithPushTemplate();
        Queue::fake([DeliverNotification::class]);
        Http::preventStrayRequests();
        Http::fake(['https://api.onesignal.com/notifications' => Http::response(['id' => 'message-123'], 200)]);

        app(NotificationDispatcher::class)->queue('job.assigned', $technician, ['job_number' => 'JOB-123']);
        $log = NotificationLog::query()->firstOrFail();
        Queue::assertPushed(DeliverNotification::class, 1);
        (new DeliverNotification($log->getKey()))->handle(app(TenantContext::class));

        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $this->assertSame(NotificationStatus::Sent, $log->refresh()->status);
        $this->assertSame('onesignal', $log->provider);
        $this->assertSame('message-123', $log->provider_id);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.onesignal.com/notifications'
            && $request->hasHeader('Authorization', 'Key server-secret')
            && $request['app_id'] === 'test-app-id'
            && $request['target_channel'] === 'push'
            && $request['include_aliases'] === ['external_id' => [$technician->getKey()]]
            && $request['contents'] === ['en' => 'Job JOB-123 is ready.']
            && $request['headings'] === ['en' => 'New assignment']
            && $request['url'] === route('technician.dashboard')
            && Uuid::isValid($request['idempotency_key']));
        Http::assertSentCount(1);
    }

    public function test_onesignal_response_without_message_id_marks_delivery_failed(): void
    {
        config()->set('services.onesignal', ['app_id' => 'test-app-id', 'api_key' => 'server-secret']);
        [$tenant, $technician] = $this->technicianWithPushTemplate();
        Queue::fake([DeliverNotification::class]);
        Http::preventStrayRequests();
        Http::fake(['https://api.onesignal.com/notifications' => Http::response([], 200)]);

        app(NotificationDispatcher::class)->queue('job.assigned', $technician, ['job_number' => 'JOB-123']);
        $log = NotificationLog::query()->firstOrFail();

        try {
            (new DeliverNotification($log->getKey()))->handle(app(TenantContext::class));
            $this->fail('Delivery should fail when OneSignal creates no message.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('without creating a notification', $exception->getMessage());
        }

        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $this->assertSame(NotificationStatus::Failed, $log->refresh()->status);
        $this->assertSame(1, $log->attempts);
        Http::assertSentCount(1);
    }

    public function test_legacy_push_provider_remains_available_without_onesignal_credentials(): void
    {
        config()->set('services.onesignal', ['app_id' => null, 'api_key' => null]);
        config()->set('services.push.url', 'https://push.example.test/send');
        config()->set('services.push.token', 'legacy-secret');
        [$tenant, $technician] = $this->technicianWithPushTemplate();
        Queue::fake([DeliverNotification::class]);
        $technician->pushSubscriptions()->create([
            'endpoint_hash' => hash('sha256', 'https://push.example.test/sub/1'),
            'endpoint' => 'https://push.example.test/sub/1',
            'public_key' => 'public-key',
            'auth_token' => 'auth-token',
            'last_used_at' => now(),
        ]);
        Http::preventStrayRequests();
        Http::fake(['https://push.example.test/send' => Http::response(['id' => 'legacy-message'], 200)]);

        app(NotificationDispatcher::class)->queue('job.assigned', $technician, ['job_number' => 'JOB-123']);
        $log = NotificationLog::query()->firstOrFail();
        (new DeliverNotification($log->getKey()))->handle(app(TenantContext::class));

        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $this->assertSame(NotificationStatus::Sent, $log->refresh()->status);
        $this->assertSame('PUSH', $log->provider);
        $this->assertSame('legacy-message', $log->provider_id);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://push.example.test/send'
            && $request['endpoint'] === 'https://push.example.test/sub/1');
        Http::assertSentCount(1);
    }

    public function test_mobile_portal_exposes_app_id_but_not_onesignal_api_key(): void
    {
        config()->set('services.onesignal', ['app_id' => 'test-app-id', 'api_key' => 'server-secret']);
        $tenant = Tenant::factory()->create();
        $technician = User::factory()->for($tenant)->technician()->create();

        $this->actingAs($technician)->get(route('technician.dashboard'))
            ->assertOk()
            ->assertSee('name="onesignal-app-id" content="test-app-id"', false)
            ->assertSee('name="onesignal-external-id" content="'.$technician->getKey().'"', false)
            ->assertDontSee('server-secret');
    }

    /** @return array{Tenant, User} */
    private function technicianWithPushTemplate(): array
    {
        $tenant = Tenant::factory()->create();
        $technician = User::factory()->for($tenant)->technician()->create();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        NotificationTemplate::query()->create([
            'event' => 'job.assigned',
            'channel' => NotificationChannel::Push,
            'locale' => 'en_IN',
            'subject' => 'New assignment',
            'body' => 'Job {{job_number}} is ready.',
            'is_active' => true,
        ]);

        return [$tenant, $technician];
    }
}
