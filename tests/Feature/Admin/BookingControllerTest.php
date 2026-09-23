<?php

namespace Tests\Feature\Admin;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BookingControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();

        parent::tearDown();
    }

    public function test_renders_booking_calendar_and_inline_customer_creation_for_owner(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 24)->startOfDay());
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->create();
        $this->app->make(TenantContext::class)->set($tenant->getKey());

        $this->actingAs($owner)
            ->get(route('admin.bookings.index'))
            ->assertSee('Booking calendar')
            ->assertSee('data-month="2026-09"', false)
            ->assertSee('Add customer')
            ->assertSee('data-rich-table="server"', false);
    }

    public function test_calendar_returns_active_bookings_for_requested_month_and_current_tenant_only(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->create();
        $customer = Customer::factory()->for($tenant)->create();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $this->actingAs($owner);

        foreach ([
            ['2026-09-25 10:00:00', BookingStatus::Pending],
            ['2026-09-25 16:00:00', BookingStatus::Converted],
            ['2026-09-25 17:00:00', BookingStatus::Cancelled],
            ['2026-10-01 10:00:00', BookingStatus::Pending],
        ] as [$preferredAt, $status]) {
            Booking::query()->create([
                'customer_id' => $customer->getKey(),
                'booking_number' => 'BK-'.str_replace([' ', ':', '-'], '', $preferredAt),
                'channel' => 'PHONE',
                'status' => $status,
                'service_type' => 'AC service',
                'preferred_start_at' => $preferredAt,
            ]);
        }

        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->for($otherTenant)->create();
        $this->app->make(TenantContext::class)->set($otherTenant->getKey());
        Booking::query()->create([
            'customer_id' => $otherCustomer->getKey(),
            'booking_number' => 'BK-OTHER-TENANT',
            'channel' => 'PHONE',
            'status' => BookingStatus::Pending,
            'service_type' => 'AC service',
            'preferred_start_at' => '2026-09-25 11:00:00',
        ]);
        $this->app->make(TenantContext::class)->set($tenant->getKey());

        $this->getJson(route('admin.bookings.index', ['calendar_month' => '2026-09']))
            ->assertOk()
            ->assertExactJson([
                'month' => '2026-09',
                'counts' => ['2026-09-25' => 2],
            ]);
    }

    public function test_calendar_rejects_invalid_month(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->create();
        $this->app->make(TenantContext::class)->set($tenant->getKey());

        $this->actingAs($owner)
            ->getJson(route('admin.bookings.index', ['calendar_month' => '2026-13']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('calendar_month');
    }
}
