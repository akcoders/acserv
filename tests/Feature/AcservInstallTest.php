<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Support\TenantContext;
use Database\Seeders\CommerceDemoSeeder;
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
        $this->assertDatabaseHas('vendors', ['code' => 'VEN-DEMO-001', 'name' => 'CoolTech Spare Parts']);
        $this->assertDatabaseHas('purchase_orders', ['order_number' => 'PO-DEMO-001', 'status' => 'RECEIVED', 'grand_total' => 1416]);
        $this->assertDatabaseHas('account_entries', ['entry_number' => 'ACC-DEMO-001', 'type' => 'PURCHASE_PAYMENT', 'amount' => 500]);
        $this->assertDatabaseHas('account_entries', ['entry_number' => 'ACC-DEMO-002', 'type' => 'EXPENSE', 'amount' => 180]);
        $this->assertDatabaseHas('employment_profiles', ['employee_code' => 'TECH-DEMO-001', 'pay_grade' => 'T2', 'monthly_salary' => 28000]);
        $this->assertDatabaseCount('tenants', 1);
        $this->assertDatabaseCount('vendors', 1);
        $this->assertDatabaseCount('purchase_orders', 1);
        $this->assertDatabaseCount('account_entries', 2);
        $this->assertDatabaseCount('employment_profiles', 1);
    }

    public function test_commerce_demo_records_remain_tenant_scoped_and_are_safe_to_seed_twice(): void
    {
        $this->artisan('acserv:install', [
            '--demo' => true,
            '--no-storage-link' => true,
            '--no-optimize' => true,
        ])->assertSuccessful();
        $tenant = Tenant::query()->where('slug', 'acserv-demo')->firstOrFail();

        $this->artisan('db:seed', ['--class' => CommerceDemoSeeder::class, '--no-interaction' => true])->assertSuccessful();
        $this->artisan('db:seed', ['--class' => CommerceDemoSeeder::class, '--no-interaction' => true])->assertSuccessful();

        $this->assertDatabaseCount('vendors', 1);
        $this->assertDatabaseCount('purchase_orders', 1);
        $this->assertDatabaseCount('purchase_order_lines', 1);
        $this->assertDatabaseCount('account_entries', 2);
        $this->assertDatabaseCount('employment_profiles', 1);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseHas('stock_movements', ['tenant_id' => $tenant->getKey(), 'idempotency_key' => 'demo-purchase:PO-DEMO-001', 'type' => 'PURCHASE', 'quantity' => 4]);
        $this->assertDatabaseHas('inventory_items', ['tenant_id' => $tenant->getKey(), 'sku' => 'CAP-35UF', 'unit_cost' => 300]);
        $this->assertDatabaseHas('vendors', ['tenant_id' => $tenant->getKey(), 'code' => 'VEN-DEMO-001']);
        $this->assertDatabaseHas('purchase_orders', ['tenant_id' => $tenant->getKey(), 'order_number' => 'PO-DEMO-001']);
        $this->assertDatabaseHas('account_entries', ['tenant_id' => $tenant->getKey(), 'entry_number' => 'ACC-DEMO-001']);
        $this->assertDatabaseHas('employment_profiles', ['tenant_id' => $tenant->getKey(), 'employee_code' => 'TECH-DEMO-001']);
        $this->assertNull(app(TenantContext::class)->id());
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
