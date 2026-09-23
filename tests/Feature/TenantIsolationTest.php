<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use LogicException;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();

        parent::tearDown();
    }

    public function test_tenant_model_queries_return_no_records_without_a_tenant_context(): void
    {
        $tenant = Tenant::factory()->create();
        Branch::factory()->for($tenant)->create();

        $this->assertSame(0, Branch::query()->count());
    }

    public function test_tenant_model_queries_only_return_records_for_the_active_tenant(): void
    {
        $firstTenant = Tenant::factory()->create();
        $secondTenant = Tenant::factory()->create();
        $visibleBranch = Branch::factory()->for($firstTenant)->create();
        Branch::factory()->for($secondTenant)->create();
        $this->app->make(TenantContext::class)->set($firstTenant->id);

        $branches = Branch::query()->get();

        $this->assertCount(1, $branches);
        $this->assertTrue($branches->first()->is($visibleBranch));
    }

    public function test_active_tenant_is_assigned_when_a_tenant_record_is_created(): void
    {
        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->id);
        $branch = Branch::factory()->make(['tenant_id' => null]);

        $branch->save();

        $this->assertSame($tenant->id, $branch->tenant_id);
        $this->assertModelExists($branch);
    }

    public function test_creating_a_tenant_record_without_tenant_identity_is_rejected(): void
    {
        $branch = Branch::factory()->make(['tenant_id' => null]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A tenant context is required');

        $branch->save();
    }
}
