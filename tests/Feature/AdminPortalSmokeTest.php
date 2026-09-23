<?php

namespace Tests\Feature;

use App\Enums\JobStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Job;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Inventory\StockService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdminPortalSmokeTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();

        parent::tearDown();
    }

    public function test_owner_can_open_every_main_admin_page(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->create();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $this->actingAs($owner);

        foreach ([
            'admin.dashboard',
            'admin.bookings.index',
            'admin.jobs.index',
            'admin.inventory.index',
            'admin.billing.index',
            'admin.warranties.index',
            'admin.workforce.index',
            'admin.notifications.index',
            'admin.cms.index',
            'admin.analytics.index',
            'admin.readiness',
        ] as $routeName) {
            $this->get(route($routeName))->assertOk();
        }
    }

    public function test_owner_can_add_inventory_item_and_see_stock_summary(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->create();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $this->actingAs($owner);

        $this->postJson(route('admin.inventory.store'), [
            'sku' => 'SMOKE-FILTER-01',
            'name' => 'Smoke test filter',
            'unit' => 'PCS',
            'unit_cost' => 100,
            'sale_price' => 180,
            'reorder_level' => 2,
            'is_active' => true,
            'track_serials' => false,
        ])->assertCreated();

        $this->get(route('admin.inventory.index'))
            ->assertOk()
            ->assertSee('SMOKE-FILTER-01')
            ->assertSee('Available stock')
            ->assertSee('Used on jobs');

        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $item = InventoryItem::query()->where('sku', 'SMOKE-FILTER-01')->firstOrFail();
        $this->putJson(route('admin.inventory.update', $item), [
            'sku' => $item->sku,
            'name' => 'Updated smoke test filter',
            'unit' => 'PCS',
            'unit_cost' => 110,
            'sale_price' => 190,
            'reorder_level' => 2,
            'is_active' => true,
            'track_serials' => false,
        ])->assertOk();
        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->getKey(),
            'name' => 'Updated smoke test filter',
        ]);

        $this->postJson(route('admin.stock-locations.store'), [
            'code' => 'SMOKE-MAIN',
            'name' => 'Smoke warehouse',
            'type' => 'WAREHOUSE',
        ])->assertCreated();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $location = StockLocation::query()->where('code', 'SMOKE-MAIN')->firstOrFail();
        $this->postJson(route('admin.stock-movements.store'), [
            'inventory_item_id' => $item->getKey(),
            'to_location_id' => $location->getKey(),
            'type' => 'OPENING',
            'quantity' => 5,
            'unit_cost' => 110,
        ])->assertCreated();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $this->assertSame(5.0, $this->app->make(StockService::class)->balance($item, $location));

        $this->deleteJson(route('admin.inventory.destroy', $item))->assertUnprocessable();
    }

    public function test_job_part_consumption_reduces_available_stock_on_admin_inventory_page(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->create();
        $customer = Customer::factory()->for($tenant)->create();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $this->actingAs($owner);

        $item = InventoryItem::query()->create([
            'sku' => 'CONSUMED-FILTER',
            'name' => 'Filter used on a job',
            'unit' => 'PCS',
            'unit_cost' => 100,
            'sale_price' => 180,
            'reorder_level' => 1,
            'is_active' => true,
        ]);
        $location = StockLocation::query()->create([
            'code' => 'MAIN',
            'name' => 'Main warehouse',
            'type' => 'WAREHOUSE',
            'is_active' => true,
        ]);
        StockMovement::query()->create([
            'inventory_item_id' => $item->getKey(),
            'to_location_id' => $location->getKey(),
            'type' => 'OPENING',
            'quantity' => 5,
            'unit_cost' => 100,
            'moved_at' => now(),
        ]);
        $job = Job::query()->create([
            'customer_id' => $customer->getKey(),
            'job_number' => 'JOB-STOCK-001',
            'status' => JobStatus::InProgress,
            'service_type' => 'AC repair',
        ]);

        $stockService = $this->app->make(StockService::class);
        $stockService->consume($job, $item, $location, 2, 180);

        $this->assertSame(3.0, $stockService->balance($item, $location));
        $this->get(route('admin.inventory.index'))
            ->assertOk()
            ->assertSee('3.000')
            ->assertSee('2.000');
    }

    public function test_owner_can_manage_booking_and_dispatch_job_from_admin(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->create();
        $technician = User::factory()->for($tenant)->technician()->create();
        $customer = Customer::factory()->for($tenant)->create();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $this->actingAs($owner);

        $bookingResponse = $this->postJson(route('admin.bookings.store'), [
            'customer_id' => $customer->getKey(),
            'channel' => 'PHONE',
            'service_type' => 'AC repair',
            'complaint' => 'Cooling is weak',
        ])->assertCreated();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $booking = Booking::query()->findOrFail($bookingResponse->json('booking.id'));
        $this->putJson(route('admin.bookings.update', $booking), [
            'customer_id' => $customer->getKey(),
            'channel' => 'PHONE',
            'service_type' => 'AC repair',
            'complaint' => 'Compressor needs inspection',
        ])->assertOk();
        $this->assertDatabaseHas('bookings', ['id' => $booking->getKey(), 'complaint' => 'Compressor needs inspection']);

        $jobResponse = $this->postJson(route('admin.jobs.store'), [
            'booking_id' => $booking->getKey(),
            'customer_id' => $customer->getKey(),
            'priority' => 'NORMAL',
            'service_type' => 'AC repair',
            'description' => 'Inspect compressor',
            'checklist' => ['Inspect compressor'],
        ])->assertCreated();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $job = Job::query()->findOrFail($jobResponse->json('job.id'));

        $this->postJson(route('admin.jobs.assignments.store', $job), [
            'technician_id' => $technician->getKey(),
        ])->assertOk();
        $this->get(route('admin.jobs.show', $job))
            ->assertOk()
            ->assertSee($job->job_number)
            ->assertSee('Job timeline')
            ->assertSee('Inspect compressor');
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $this->assertSame(JobStatus::Assigned, $job->refresh()->status);

        $this->deleteJson(route('admin.bookings.destroy', $booking))->assertOk();
        $this->assertSoftDeleted($booking);
        $this->deleteJson(route('admin.jobs.destroy', $job))->assertUnprocessable();
    }
}
