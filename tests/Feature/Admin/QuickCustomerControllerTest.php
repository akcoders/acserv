<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class QuickCustomerControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();

        parent::tearDown();
    }

    public function test_owner_can_create_a_customer_without_leaving_booking_modal(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->create();
        $otherTenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->getKey());

        $response = $this->actingAs($owner)->postJson(route('admin.customers.quick.store'), [
            'name' => 'Asha Sharma',
            'phone' => '+919876543210',
            'email' => 'asha@example.test',
            'tenant_id' => $otherTenant->getKey(),
        ])->assertCreated()
            ->assertJsonPath('customer.name', 'Asha Sharma')
            ->assertJsonPath('customer.phone', '+919876543210');

        $this->assertDatabaseHas('customers', [
            'id' => $response->json('customer.id'),
            'tenant_id' => $tenant->getKey(),
            'name' => 'Asha Sharma',
            'email' => 'asha@example.test',
        ]);
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $this->assertSame(1, Customer::query()->where('phone', '+919876543210')->count());
    }

    public function test_quick_customer_requires_name_and_phone(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->create();
        $this->app->make(TenantContext::class)->set($tenant->getKey());

        $this->actingAs($owner)
            ->postJson(route('admin.customers.quick.store'), ['email' => 'invalid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'phone', 'email']);

        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $this->assertSame(0, Customer::query()->count());
    }

    public function test_technician_cannot_quick_create_customer(): void
    {
        $tenant = Tenant::factory()->create();
        $technician = User::factory()->for($tenant)->technician()->create();
        $this->app->make(TenantContext::class)->set($tenant->getKey());

        $this->actingAs($technician)
            ->postJson(route('admin.customers.quick.store'), [
                'name' => 'Asha Sharma',
                'phone' => '+919876543210',
            ])
            ->assertForbidden();

        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $this->assertSame(0, Customer::query()->count());
    }
}
