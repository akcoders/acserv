<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AcservInstallTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_empty_database_creates_live_owner_and_branch_once(): void
    {
        config()->set('acserv.public_tenant_slug', 'acserv-live');
        $options = [
            '--workspace' => 'acserv-live',
            '--owner-email' => 'owner@example.com',
            '--company' => 'ACServ Live',
            '--no-storage-link' => true,
            '--no-optimize' => true,
        ];

        $this->artisan('acserv:install', $options)->assertSuccessful();
        $this->artisan('acserv:install', $options)->assertSuccessful();

        $this->assertDatabaseHas('tenants', ['slug' => 'acserv-live', 'name' => 'ACServ Live']);
        $this->assertDatabaseHas('users', ['email' => 'owner@example.com', 'role' => 'OWNER']);
        $this->assertDatabaseHas('branches', ['code' => 'MAIN']);
        $this->assertDatabaseCount('tenants', 1);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_demo_option_seeds_only_an_empty_database(): void
    {
        $options = [
            '--demo' => true,
            '--no-storage-link' => true,
            '--no-optimize' => true,
        ];

        $this->artisan('acserv:install', $options)->assertSuccessful();
        $this->artisan('acserv:install', $options)->assertSuccessful();

        $this->assertDatabaseHas('tenants', ['slug' => 'acserv-demo']);
        $this->assertDatabaseHas('users', ['email' => 'owner@acserv.test', 'role' => 'OWNER']);
        $this->assertDatabaseHas('users', ['email' => 'technician@acserv.test', 'role' => 'TECHNICIAN']);
        $this->assertDatabaseHas('users', ['email' => 'customer@acserv.test', 'role' => 'CUSTOMER']);
        $this->assertDatabaseHas('jobs', ['job_number' => 'JOB-DEMO-001']);
        $this->assertDatabaseCount('tenants', 1);
    }

    public function test_existing_workspace_is_not_replaced_with_demo_data(): void
    {
        Tenant::factory()->create(['slug' => 'customer-workspace']);

        $this->artisan('acserv:install', [
            '--demo' => true,
            '--no-storage-link' => true,
            '--no-optimize' => true,
        ])->assertSuccessful();

        $this->assertDatabaseCount('tenants', 1);
        $this->assertDatabaseMissing('tenants', ['slug' => 'acserv-demo']);
    }

    public function test_production_debug_mode_is_rejected_before_database_changes(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config()->set('app.debug', true);

        $this->artisan('acserv:install', [
            '--demo' => true,
            '--no-storage-link' => true,
            '--no-optimize' => true,
        ])->assertFailed();

        $this->assertDatabaseCount('tenants', 0);
    }
}
