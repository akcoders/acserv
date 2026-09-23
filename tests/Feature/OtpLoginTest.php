<?php

namespace Tests\Feature;

use App\Auth\OtpCodeGenerator;
use App\Enums\OtpChannel;
use App\Enums\OtpPurpose;
use App\Models\Branch;
use App\Models\OtpChallenge;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\OtpCodeNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OtpLoginTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_page_renders_the_otp_form(): void
    {
        $response = $this->get(route('login'));

        $response
            ->assertOk()
            ->assertSee(__('auth.login_title'))
            ->assertSee('data-ajax', escape: false);
    }

    public function test_local_login_page_prefills_demo_owner_credentials(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        config()->set('acserv.demo_login', [
            'workspace' => 'cool-air',
            'owner_email' => 'owner@example.com',
        ]);

        $response = $this->get(route('login'));

        $response
            ->assertOk()
            ->assertSee('value="cool-air"', escape: false)
            ->assertSee('value="owner@example.com"', escape: false);
    }

    public function test_valid_account_request_creates_challenge_and_sends_code(): void
    {
        Notification::fake();
        $this->mock(OtpCodeGenerator::class)
            ->shouldReceive('generate')
            ->once()
            ->andReturn('123456');
        $tenant = Tenant::factory()->create(['slug' => 'cool-air']);
        $user = User::factory()->for($tenant)->create(['email' => 'owner@example.com']);

        $response = $this->postJson(route('login.otp.store'), [
            'tenant' => 'cool-air',
            'login' => 'OWNER@EXAMPLE.COM',
            'channel' => OtpChannel::Email->value,
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => __('auth.code_sent'),
                'show_verification' => true,
            ])
            ->assertJsonMissingPath('debug_otp');
        Notification::assertSentOnDemand(OtpCodeNotification::class);
        $challenge = OtpChallenge::query()->sole();
        $this->assertSame($user->id, $challenge->user_id);
        $this->assertSame(OtpPurpose::Login, $challenge->purpose);
        $this->assertTrue(Hash::check('123456', $challenge->code_hash));
    }

    public function test_local_log_mail_response_includes_code_for_manual_testing(): void
    {
        Notification::fake();
        $this->app->detectEnvironment(fn (): string => 'local');
        config()->set('mail.default', 'log');
        $this->mock(OtpCodeGenerator::class)
            ->shouldReceive('generate')
            ->once()
            ->andReturn('123456');
        $tenant = Tenant::factory()->create(['slug' => 'cool-air']);
        User::factory()->for($tenant)->create(['email' => 'owner@example.com']);

        $response = $this->withSession(['_token' => 'local-test-token'])->postJson(route('login.otp.store'), [
            '_token' => 'local-test-token',
            'tenant' => 'cool-air',
            'login' => 'owner@example.com',
            'channel' => OtpChannel::Email->value,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', __('auth.local_code_ready'))
            ->assertJsonPath('debug_otp', '123456');
        Notification::assertSentOnDemand(OtpCodeNotification::class);
        $this->assertTrue(Hash::check('123456', OtpChallenge::query()->sole()->code_hash));
    }

    public function test_local_unknown_account_returns_422_without_showing_verification(): void
    {
        Notification::fake();
        $this->app->detectEnvironment(fn (): string => 'local');

        $response = $this->withSession(['_token' => 'local-test-token'])->postJson(route('login.otp.store'), [
            '_token' => 'local-test-token',
            'tenant' => 'acserv-demo',
            'login' => 'owner@example.com',
            'channel' => OtpChannel::Email->value,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['login'])
            ->assertJsonPath('errors.login.0', __('auth.local_account_not_found'));
        $this->assertDatabaseEmpty('otp_challenges');
        Notification::assertNothingSent();
    }

    public function test_unknown_account_returns_generic_success_without_creating_challenge(): void
    {
        Notification::fake();

        $response = $this->postJson(route('login.otp.store'), [
            'tenant' => 'unknown-workspace',
            'login' => 'unknown@example.com',
            'channel' => OtpChannel::Email->value,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', __('auth.code_sent'));
        $this->assertDatabaseEmpty('otp_challenges');
        Notification::assertNothingSent();
    }

    public function test_valid_code_authenticates_user_and_consumes_challenge(): void
    {
        Notification::fake();
        $this->mock(OtpCodeGenerator::class)
            ->shouldReceive('generate')
            ->once()
            ->andReturn('123456');
        $tenant = Tenant::factory()->create(['slug' => 'cool-air']);
        $user = User::factory()->for($tenant)->create(['email' => 'owner@example.com']);
        $payload = [
            'tenant' => 'cool-air',
            'login' => 'owner@example.com',
            'channel' => OtpChannel::Email->value,
        ];
        $this->postJson(route('login.otp.store'), $payload)->assertOk();

        $response = $this->postJson(route('login.otp.verify'), [
            ...$payload,
            'code' => '123456',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('redirect', route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull(OtpChallenge::query()->sole()->consumed_at);
    }

    public function test_invalid_code_returns_422_and_increments_attempt_count(): void
    {
        Notification::fake();
        $this->mock(OtpCodeGenerator::class)
            ->shouldReceive('generate')
            ->once()
            ->andReturn('123456');
        $tenant = Tenant::factory()->create(['slug' => 'cool-air']);
        User::factory()->for($tenant)->create(['email' => 'owner@example.com']);
        $payload = [
            'tenant' => 'cool-air',
            'login' => 'owner@example.com',
            'channel' => OtpChannel::Email->value,
        ];
        $this->postJson(route('login.otp.store'), $payload)->assertOk();

        $response = $this->postJson(route('login.otp.verify'), [
            ...$payload,
            'code' => '654321',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
        $this->assertGuest();
        $this->assertSame(1, OtpChallenge::query()->sole()->attempts);
    }

    public function test_expired_code_returns_422_and_does_not_authenticate(): void
    {
        Notification::fake();
        $this->mock(OtpCodeGenerator::class)
            ->shouldReceive('generate')
            ->once()
            ->andReturn('123456');
        $tenant = Tenant::factory()->create(['slug' => 'cool-air']);
        User::factory()->for($tenant)->create(['email' => 'owner@example.com']);
        $payload = [
            'tenant' => 'cool-air',
            'login' => 'owner@example.com',
            'channel' => OtpChannel::Email->value,
        ];
        $this->postJson(route('login.otp.store'), $payload)->assertOk();
        $this->travel(6)->minutes();

        $response = $this->postJson(route('login.otp.verify'), [
            ...$payload,
            'code' => '123456',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
        $this->assertGuest();
    }

    public function test_empty_otp_request_returns_422_with_field_messages(): void
    {
        $response = $this->postJson(route('login.otp.store'));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tenant', 'login', 'channel']);
    }

    public function test_guest_is_redirected_from_admin_dashboard_to_login(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirectToRoute('login');
    }

    public function test_authenticated_user_dashboard_uses_only_their_tenant_context(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Visible Tenant']);
        $otherTenant = Tenant::factory()->create(['name' => 'Hidden Tenant']);
        $user = User::factory()->for($tenant)->create();
        Branch::factory()->for($tenant)->create();
        Branch::factory()->for($otherTenant)->count(2)->create();

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response
            ->assertOk()
            ->assertSee('Visible Tenant')
            ->assertDontSee('Hidden Tenant')
            ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['branches'] === 1);
    }
}
