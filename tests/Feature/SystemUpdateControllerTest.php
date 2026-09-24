<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Operations\SystemUpdateService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery\MockInterface;
use Tests\TestCase;

class SystemUpdateControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();

        parent::tearDown();
    }

    public function test_only_the_public_workspace_owner_can_open_the_updater(): void
    {
        $publicTenant = Tenant::factory()->create(['slug' => config('acserv.public_tenant_slug')]);
        $otherTenant = Tenant::factory()->create();
        $publicAdmin = User::factory()->for($publicTenant)->create(['role' => Role::Admin]);
        $otherOwner = User::factory()->for($otherTenant)->create();
        $publicOwner = User::factory()->for($publicTenant)->create();

        $this->get(route('admin.system-updates.index'))->assertRedirect(route('login'));
        $this->actingAs($publicAdmin)->get(route('admin.system-updates.index'))->assertForbidden();
        $this->actingAs($otherOwner)->get(route('admin.system-updates.index'))->assertForbidden();

        $this->mock(SystemUpdateService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('ensureAccessToken')->once();
            $mock->shouldReceive('accessTokenPath')->once()->andReturn('/private/updates/access-key');
        });

        $this->actingAs($publicOwner)->get(route('admin.system-updates.index'))
            ->assertOk()
            ->assertSee('Application updates')
            ->assertSee('/private/updates/access-key');
    }

    public function test_staging_requires_a_valid_private_key_and_returns_a_review_url(): void
    {
        $this->signInAsPublicOwner();

        $this->mock(SystemUpdateService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('hasValidToken')->twice()->andReturn(false, true);
            $mock->shouldReceive('stage')->once()->andReturn([
                'id' => 'release-123',
                'version' => '1.2.3',
                'file_count' => 2,
                'total_bytes' => 128,
            ]);
        });

        $invalid = $this->postJson(route('admin.system-updates.store'), [
            'access_key' => str_repeat('a', 64),
            'package' => UploadedFile::fake()->create('release.zip', 1, 'application/zip'),
        ]);
        $invalid->assertUnprocessable()->assertJsonValidationErrors('access_key');

        $this->postJson(route('admin.system-updates.store'), [
            'access_key' => str_repeat('b', 64),
            'package' => UploadedFile::fake()->create('release.zip', 1, 'application/zip'),
        ])->assertCreated()->assertJsonPath('redirect', route('admin.system-updates.index', ['id' => 'release-123']));
    }

    public function test_apply_requires_backup_confirmation_and_queues_the_release(): void
    {
        $owner = $this->signInAsPublicOwner();

        $this->mock(SystemUpdateService::class, function (MockInterface $mock) use ($owner): void {
            $mock->shouldReceive('hasValidToken')->once()->andReturn(true);
            $mock->shouldReceive('queue')->once()->with('release-123', public_path(), (string) $owner->getKey(), 'hostinger-full-backup-2026-09-24');
        });

        $this->postJson(route('admin.system-updates.apply', ['id' => 'release-123']), [
            'access_key' => str_repeat('a', 64),
            'backup_reference' => 'hostinger-full-backup-2026-09-24',
        ])->assertUnprocessable()->assertJsonValidationErrors(['confirm_backup', 'confirm_downtime']);

        $this->postJson(route('admin.system-updates.apply', ['id' => 'release-123']), [
            'access_key' => str_repeat('a', 64),
            'backup_reference' => 'hostinger-full-backup-2026-09-24',
            'confirm_backup' => '1',
            'confirm_downtime' => '1',
        ])->assertOk()->assertJsonPath('redirect', route('admin.system-updates.index', ['id' => 'release-123']));
    }

    public function test_another_workspace_owner_cannot_upload_or_apply_a_release(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAs(User::factory()->for($tenant)->create());

        $this->postJson(route('admin.system-updates.store'), [
            'access_key' => str_repeat('a', 64),
            'package' => UploadedFile::fake()->create('release.zip', 1, 'application/zip'),
        ])->assertForbidden();

        $this->postJson(route('admin.system-updates.apply', ['id' => str_repeat('a', 32)]), [
            'access_key' => str_repeat('a', 64),
            'backup_reference' => 'verified-backup',
            'confirm_backup' => '1',
            'confirm_downtime' => '1',
        ])->assertForbidden();
    }

    public function test_staged_release_is_rendered_with_apply_form(): void
    {
        $this->signInAsPublicOwner();
        $id = str_repeat('a', 32);

        $this->mock(SystemUpdateService::class, function (MockInterface $mock) use ($id): void {
            $mock->shouldReceive('ensureAccessToken')->once();
            $mock->shouldReceive('accessTokenPath')->once()->andReturn('/private/updates/access-key');
            $mock->shouldReceive('status')->once()->with($id)->andReturn([
                'id' => $id,
                'version' => '2026.09.24',
                'file_count' => 12,
                'total_bytes' => 2048,
                'state' => 'staged',
            ]);
        });

        $this->get(route('admin.system-updates.index', ['id' => $id]))
            ->assertOk()
            ->assertSee('2026.09.24')
            ->assertSee('Queue application update');
    }

    public function test_status_response_does_not_expose_private_paths_or_backup_reference(): void
    {
        $this->signInAsPublicOwner();
        $id = str_repeat('b', 32);

        $this->mock(SystemUpdateService::class, function (MockInterface $mock) use ($id): void {
            $mock->shouldReceive('status')->once()->with($id)->andReturn([
                'id' => $id,
                'version' => '1.2.3',
                'state' => 'queued',
                'web_root' => '/private/webroot',
                'backup_reference' => 'private-backup-name',
                'archive_sha256' => str_repeat('a', 64),
            ]);
        });

        $this->getJson(route('admin.system-updates.status', ['id' => $id]))
            ->assertOk()
            ->assertJsonPath('update.state', 'queued')
            ->assertJsonMissingPath('update.web_root')
            ->assertJsonMissingPath('update.backup_reference')
            ->assertJsonMissingPath('update.archive_sha256');
    }

    private function signInAsPublicOwner(): User
    {
        $tenant = Tenant::factory()->create(['slug' => config('acserv.public_tenant_slug')]);
        $owner = User::factory()->for($tenant)->create();
        $this->actingAs($owner);

        return $owner;
    }
}
